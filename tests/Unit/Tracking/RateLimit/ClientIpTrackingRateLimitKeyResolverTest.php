<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Unit\Tracking\RateLimit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Zhortein\SeoTrackingBundle\Tracking\RateLimit\ClientIpTrackingRateLimitKeyResolver;
use Zhortein\SeoTrackingBundle\Tracking\RateLimit\TrackingEndpoint;

final class ClientIpTrackingRateLimitKeyResolverTest extends TestCase
{
    public function testItReturnsAStableHashInsteadOfTheRawClientIp(): void
    {
        $request = new Request(server: ['REMOTE_ADDR' => '203.0.113.42']);
        $resolver = new ClientIpTrackingRateLimitKeyResolver();

        $key = $resolver->resolve($request, TrackingEndpoint::CREATION);

        self::assertSame(hash('sha256', '203.0.113.42'), $key);
        self::assertStringNotContainsString('203.0.113.42', $key);
        self::assertSame($key, $resolver->resolve($request, TrackingEndpoint::CLOSURE));
    }

    public function testRequestsWithoutAResolvedClientIpShareAnExplicitFallbackKey(): void
    {
        $key = (new ClientIpTrackingRateLimitKeyResolver())->resolve(
            new Request(),
            TrackingEndpoint::CREATION,
        );

        self::assertSame(hash('sha256', 'unknown'), $key);
    }
}
