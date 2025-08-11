<?php

namespace Zhortein\SeoTrackingBundle\EventListener;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpClient\HttpClient;
use Zhortein\SeoTrackingBundle\DTO\SeoTrackingOptions;
use Zhortein\SeoTrackingBundle\Event\PageCallExitEvent;
use Zhortein\SeoTrackingBundle\Event\PageCallTrackedEvent;

readonly class PageCallTrackedListener
{
    public function __construct(
        private SeoTrackingOptions $seoTrackingOptions,
        private LoggerInterface $logger,
    ) {
    }

    private function sendApiRequest(string $url, array $payload): void
    {
        try {
            $httpClient = HttpClient::create();
            $url = rtrim($url, '/');
            $response = $httpClient->request('POST', $url, [
                'headers' => [
                    'X-Api-Key' => (string) $this->seoTrackingOptions->apiKey,
                ],
                'json' => $payload,
                'timeout' => $this->seoTrackingOptions->timeout,
            ]);

            $status = $response->getStatusCode();
            if ($status >= 400) {
                $this->logger->warning('Easylyze ingestion returned non-2xx', [
                    'status' => $status,
                    'body' => $this->safeContent($response),
                ]);
            }
        } catch (\Throwable $e) {
            $this->logger->error('Easylyze ingestion failed', [
                'error' => $e->getMessage(),
                'url' => $url,
            ]);
        }
    }

    #[AsEventListener]
    public function onPageCallExit(PageCallExitEvent $event): void
    {
        if (!$this->seoTrackingOptions->enable || empty($this->seoTrackingOptions->pageExitEndpoint) || empty($this->seoTrackingOptions->apiKey)) {
            return;
        }

        $pageCall = $event->getPageCall();
        $pageCallHit = $event->getPageCallHit();

        // 🔧 Mapping → structure attendue par Easylyze::handleOutgoingHit()
        $payload = [
            'pageCallId' => $pageCall->getId(),
            'hitId' => $pageCallHit->getId(),
            'exitedAt' => $this->fmtDate(method_exists($pageCallHit, 'getExitedAt') ? $pageCallHit->getExitedAt() : null),
        ];

        $this->sendApiRequest($this->seoTrackingOptions->pageExitEndpoint, $payload);
    }

    #[AsEventListener]
    public function onPageCallTracked(PageCallTrackedEvent $event): void
    {
        if (!$this->seoTrackingOptions->enable || empty($this->seoTrackingOptions->pageCallEndpoint) || empty($this->seoTrackingOptions->apiKey)) {
            return;
        }

        $pageCall = $event->getPageCall();
        $pageCallHit = $event->getPageCallHit();

        // 🔧 Mapping → structure attendue par Easylyze::handleIncomingHit()
        $payload = [
            // PageCall
            'pageCallId' => $pageCall->getId(),
            'url' => method_exists($pageCall, 'getUrl') ? (string) $pageCall->getUrl() : null,
            'route' => method_exists($pageCall, 'getRoute') ? $pageCall->getRoute() : null,
            'routeArgs' => method_exists($pageCall, 'getRouteArgs') ? (array) $pageCall->getRouteArgs() : [],
            'campaign' => method_exists($pageCall, 'getCampaign') ? $pageCall->getCampaign() : null,
            'medium' => method_exists($pageCall, 'getMedium') ? $pageCall->getMedium() : null,
            'source' => method_exists($pageCall, 'getSource') ? $pageCall->getSource() : null,
            'term' => method_exists($pageCall, 'getTerm') ? $pageCall->getTerm() : null,
            'content' => method_exists($pageCall, 'getContent') ? $pageCall->getContent() : null,
            'firstCalledAt' => $this->fmtDate(method_exists($pageCall, 'getFirstCalledAt') ? $pageCall->getFirstCalledAt() : null),
            'lastCalledAt' => $this->fmtDate(method_exists($pageCall, 'getLastCalledAt') ? $pageCall->getLastCalledAt() : null),
            'bot' => method_exists($pageCall, 'isBot') && $pageCall->isBot(),

            // PageCallHit
            'hitId' => $pageCallHit->getId(),
            'parentHitId' => method_exists($pageCallHit, 'getParentHit') && $pageCallHit->getParentHit()?->getId(),
            'delaySincePreviousHit' => method_exists($pageCallHit, 'getDelaySincePreviousHit') ? $pageCallHit->getDelaySincePreviousHit() : null,
            'hitByBot' => method_exists($pageCallHit, 'isBot') && $pageCallHit->isBot(),
            'pageTitle' => method_exists($pageCallHit, 'getPageTitle') ? $pageCallHit->getPageTitle() : null,
            'pageType' => method_exists($pageCallHit, 'getPageType') ? $pageCallHit->getPageType() : null,
            'referrer' => method_exists($pageCallHit, 'getReferrer') ? $pageCallHit->getReferrer() : null,
            'userAgent' => method_exists($pageCallHit, 'getUserAgent') ? $pageCallHit->getUserAgent() : null,
            'anonymizedIp' => method_exists($pageCallHit, 'getAnonymizedIp') ? $pageCallHit->getAnonymizedIp() : null,
            'language' => method_exists($pageCallHit, 'getLanguage') ? $pageCallHit->getLanguage() : null,
            'screenWidth' => method_exists($pageCallHit, 'getScreenWidth') ? $pageCallHit->getScreenWidth() : null,
            'screenHeight' => method_exists($pageCallHit, 'getScreenHeight') ? $pageCallHit->getScreenHeight() : null,
            'calledAt' => $this->fmtDate(method_exists($pageCallHit, 'getCalledAt') ? $pageCallHit->getCalledAt() : null),
            'exitedAt' => $this->fmtDate(method_exists($pageCallHit, 'getExitedAt') ? $pageCallHit->getExitedAt() : null),
        ];

        $this->sendApiRequest($this->seoTrackingOptions->pageCallEndpoint, $payload);
    }

    private function fmtDate(?\DateTimeInterface $dt): ?string
    {
        return $dt?->format(\DateTimeInterface::ATOM);
    }

    private function safeContent($response): ?string
    {
        try {
            return $response->getContent(false);
        } catch (\Throwable) {
            return null;
        }
    }
}
