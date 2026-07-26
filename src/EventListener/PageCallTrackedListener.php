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

    /**
     * @param array<string, mixed> $payload
     */
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

        $payload = [
            'pageCallId' => $this->value($pageCall, 'getId'),
            'hitId' => $this->value($pageCallHit, 'getId'),
            'exitedAt' => $this->fmtDate($this->value($pageCallHit, 'getExitedAt')),
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

        $payload = [
            // PageCall
            'pageCallId' => $this->value($pageCall, 'getId'),
            'url' => $this->value($pageCall, 'getUrl'),
            'canonicalUrl' => $this->value($pageCall, 'getCanonicalUrl'),
            'route' => $this->value($pageCall, 'getRoute'),
            'routeArgs' => $this->value($pageCall, 'getRouteArgs', []),
            'campaign' => $this->value($pageCall, 'getCampaign'),
            'medium' => $this->value($pageCall, 'getMedium'),
            'source' => $this->value($pageCall, 'getSource'),
            'term' => $this->value($pageCall, 'getTerm'),
            'content' => $this->value($pageCall, 'getContent'),
            'firstCalledAt' => $this->fmtDate($this->value($pageCall, 'getFirstCalledAt')),
            'lastCalledAt' => $this->fmtDate($this->value($pageCall, 'getLastCalledAt')),
            'bot' => true === $this->value($pageCall, 'isBot', false),

            // PageCallHit
            'hitId' => $this->value($pageCallHit, 'getId'),
            'parentHitId' => $this->value($this->value($pageCallHit, 'getParentHit'), 'getId'),
            'delaySincePreviousHit' => $this->value($pageCallHit, 'getDelaySincePreviousHit'),
            'hitByBot' => true === $this->value($pageCallHit, 'isBot', false),
            'pageUrl' => $this->value($pageCallHit, 'getUrl'),
            'pageTitle' => $this->value($pageCallHit, 'getPageTitle'),
            'pageType' => $this->value($pageCallHit, 'getPageType'),
            'referrer' => $this->value($pageCallHit, 'getReferrer'),
            'userAgent' => $this->value($pageCallHit, 'getUserAgent'),
            'anonymizedIp' => $this->value($pageCallHit, 'getAnonymizedIp'),
            'language' => $this->value($pageCallHit, 'getLanguage'),
            'screenWidth' => $this->value($pageCallHit, 'getScreenWidth'),
            'screenHeight' => $this->value($pageCallHit, 'getScreenHeight'),
            'calledAt' => $this->fmtDate($this->value($pageCallHit, 'getCalledAt')),
            'exitedAt' => $this->fmtDate($this->value($pageCallHit, 'getExitedAt')),
        ];

        $this->sendApiRequest($this->seoTrackingOptions->pageCallEndpoint, $payload);
    }

    private function fmtDate(mixed $date): ?string
    {
        return $date instanceof \DateTimeInterface ? $date->format(\DateTimeInterface::ATOM) : null;
    }

    private function value(mixed $entity, string $method, mixed $default = null): mixed
    {
        if (!is_object($entity) || !is_callable([$entity, $method])) {
            return $default;
        }

        return $entity->{$method}();
    }

    private function safeContent(mixed $response): ?string
    {
        try {
            if (!is_object($response) || !is_callable([$response, 'getContent'])) {
                return null;
            }

            $content = $response->getContent(false);

            return is_string($content) ? $content : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
