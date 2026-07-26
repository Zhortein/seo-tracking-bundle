<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Attribute\AsTwigFunction;
use Zhortein\SeoTrackingBundle\Tracking\Consent\TrackingConsentCheckerInterface;

final readonly class SeoTrackingExtension
{
    private const DEFAULT_TRACKING_URL = '/zhortein/seo-tracking/page-call/track';
    private const DEFAULT_EXIT_URL = '/zhortein/seo-tracking/page-call/exit';
    private const DEFAULT_CONSENT_GRANT_EVENT = 'seo-tracking:consent-granted';
    private const DEFAULT_CONSENT_REVOKE_EVENT = 'seo-tracking:consent-revoked';

    public function __construct(
        private RequestStack $requestStack,
        private ?UrlGeneratorInterface $urlGenerator = null,
        private ?string $trackingUrl = null,
        private ?string $exitUrl = null,
        private ?TrackingConsentCheckerInterface $consentChecker = null,
        private string $consentGrantEvent = self::DEFAULT_CONSENT_GRANT_EVENT,
        private string $consentRevokeEvent = self::DEFAULT_CONSENT_REVOKE_EVENT,
    ) {
    }

    #[AsTwigFunction(name: 'seo_tracking', isSafe: ['html'])]
    public function seoTracking(string $type = 'generic', ?string $canonicalUrl = null): string
    {
        $request = $this->requestStack->getMainRequest();
        $route = $request?->attributes->get('_route', '');
        $routeArgs = $request?->attributes->get('_route_params', '{}');
        if ([] === $routeArgs) {
            $routeArgs = '{}';
        }

        $controller = 'zhortein--seo-tracking-bundle--tracking';
        $attr = sprintf('data-controller="%s"', htmlspecialchars($controller, ENT_QUOTES));

        $data = [
            'route' => $route,
            'route-args' => $routeArgs,
            'type' => $type,
            'canonical-url' => $canonicalUrl,
            'tracking-url' => $this->endpoint($this->trackingUrl, 'seo_tracking_page_call', self::DEFAULT_TRACKING_URL),
            'exit-url' => $this->endpoint($this->exitUrl, 'seo_tracking_page_exit', self::DEFAULT_EXIT_URL),
            'consent-granted' => $this->consentChecker?->isGranted($request) ?? true,
            'consent-grant-event' => $this->consentGrantEvent,
            'consent-revoke-event' => $this->consentRevokeEvent,
        ];

        foreach ($data as $key => $value) {
            $attr .= sprintf(
                ' data-%s-%s-value="%s"',
                $controller,
                $key,
                $this->encodeStimulusValue($value)
            );
        }

        return $attr;
    }

    private function endpoint(?string $configuredUrl, string $route, string $fallback): string
    {
        if (null !== $configuredUrl && '' !== $configuredUrl) {
            return $configuredUrl;
        }

        try {
            return $this->urlGenerator?->generate($route) ?? $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }

    private function encodeStimulusValue(mixed $value): string
    {
        try {
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            } elseif (is_array($value) || is_object($value)) {
                $value = json_encode($value, JSON_THROW_ON_ERROR);
            } elseif (is_scalar($value) || null === $value) {
                $value = (string) $value;
            } else {
                return '';
            }

            return htmlspecialchars($value, ENT_QUOTES);
        } catch (\Throwable) {
            return '';
        }
    }
}
