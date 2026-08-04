<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Unit\Twig;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Zhortein\SeoTrackingBundle\Tracking\Consent\TrackingConsentCheckerInterface;
use Zhortein\SeoTrackingBundle\Twig\SeoTrackingExtension;

final class SeoTrackingExtensionTest extends TestCase
{
    public function testTrackingAttributesContainRouteArgumentsAndType(): void
    {
        $request = Request::create('/article/test');
        $request->attributes->set('_route', 'article_show');
        $request->attributes->set('_route_params', ['slug' => 'test']);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $attributes = (new SeoTrackingExtension($requestStack))->seoTracking('article', dimensions: [
            'plan' => 'professional',
            'authenticated' => true,
        ]);

        self::assertStringContainsString('data-controller="zhortein--seo-tracking-bundle--tracking"', $attributes);
        self::assertStringContainsString('article_show', $attributes);
        self::assertStringContainsString('{&quot;slug&quot;:&quot;test&quot;}', $attributes);
        self::assertStringContainsString('tracking-type-value="article"', $attributes);
        self::assertStringContainsString('tracking-tracking-url-value="/zhortein/seo-tracking/page-call/track"', $attributes);
        self::assertStringContainsString('tracking-exit-url-value="/zhortein/seo-tracking/page-call/exit"', $attributes);
        self::assertStringContainsString('tracking-consent-granted-value="true"', $attributes);
        self::assertStringContainsString('tracking-consent-grant-event-value="seo-tracking:consent-granted"', $attributes);
        self::assertStringContainsString('tracking-dimensions-value="{&quot;authenticated&quot;:true,&quot;plan&quot;:&quot;professional&quot;}"', $attributes);
    }

    public function testEmptyDimensionsAreRenderedAsAStimulusObject(): void
    {
        $attributes = (new SeoTrackingExtension(new RequestStack()))->seoTracking();

        self::assertStringContainsString('tracking-dimensions-value="{}"', $attributes);
        self::assertStringNotContainsString('tracking-dimensions-value="[]"', $attributes);
    }

    public function testEndpointsFollowMountedRoutesAndCanBeOverridden(): void
    {
        $requestStack = new RequestStack();
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static fn (string $route): string => match ($route) {
                'seo_tracking_page_call' => '/mounted/track',
                'seo_tracking_page_exit' => '/mounted/exit',
                default => throw new \LogicException(sprintf('Unexpected route "%s".', $route)),
            },
        );

        $generated = (new SeoTrackingExtension($requestStack, $urlGenerator))->seoTracking(
            canonicalUrl: 'https://example.test/a?x=1&y=2',
        );
        $configured = (new SeoTrackingExtension(
            $requestStack,
            $urlGenerator,
            'https://collector.test/track',
            'https://collector.test/exit',
        ))->seoTracking();

        self::assertStringContainsString('tracking-tracking-url-value="/mounted/track"', $generated);
        self::assertStringContainsString('tracking-exit-url-value="/mounted/exit"', $generated);
        self::assertStringContainsString('https://example.test/a?x=1&amp;y=2', $generated);
        self::assertStringContainsString('tracking-tracking-url-value="https://collector.test/track"', $configured);
        self::assertStringContainsString('tracking-exit-url-value="https://collector.test/exit"', $configured);
    }

    public function testConsentCheckerAndConfiguredEventsAreRendered(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(Request::create('/pending'));
        $checker = $this->createStub(TrackingConsentCheckerInterface::class);
        $checker->method('isGranted')->willReturn(false);

        $attributes = (new SeoTrackingExtension(
            $requestStack,
            consentChecker: $checker,
            consentGrantEvent: 'cmp:analytics-granted',
            consentRevokeEvent: 'cmp:analytics-revoked',
        ))->seoTracking();

        self::assertStringContainsString('tracking-consent-granted-value="false"', $attributes);
        self::assertStringContainsString('tracking-consent-grant-event-value="cmp:analytics-granted"', $attributes);
        self::assertStringContainsString('tracking-consent-revoke-event-value="cmp:analytics-revoked"', $attributes);
    }

    public function testInvalidDimensionsFailBeforeRendering(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new SeoTrackingExtension(new RequestStack()))->seoTracking(dimensions: [
            'personal data' => 'not allowed',
        ]);
    }
}
