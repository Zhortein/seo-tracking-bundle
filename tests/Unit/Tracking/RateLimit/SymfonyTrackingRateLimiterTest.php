<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Unit\Tracking\RateLimit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Zhortein\SeoTrackingBundle\Tracking\RateLimit\ClientIpTrackingRateLimitKeyResolver;
use Zhortein\SeoTrackingBundle\Tracking\RateLimit\SymfonyTrackingRateLimiter;
use Zhortein\SeoTrackingBundle\Tracking\RateLimit\TrackingEndpoint;

final class SymfonyTrackingRateLimiterTest extends TestCase
{
    public function testOnlyConfiguredEndpointsConsumeTokens(): void
    {
        $factory = new RateLimiterFactory([
            'id' => 'seo_tracking_creation_test',
            'policy' => 'fixed_window',
            'limit' => 1,
            'interval' => '1 hour',
        ], new InMemoryStorage());
        $limiter = new SymfonyTrackingRateLimiter(
            $factory,
            null,
            new ClientIpTrackingRateLimitKeyResolver(),
        );
        $request = new Request(server: ['REMOTE_ADDR' => '203.0.113.42']);

        $accepted = $limiter->consume($request, TrackingEndpoint::CREATION);
        $rejected = $limiter->consume($request, TrackingEndpoint::CREATION);
        $unlimitedClosure = $limiter->consume($request, TrackingEndpoint::CLOSURE);

        self::assertTrue($accepted->accepted);
        self::assertFalse($rejected->accepted);
        self::assertSame(1, $rejected->limit);
        self::assertSame(0, $rejected->remainingTokens);
        self::assertNotNull($rejected->retryAfter);
        self::assertTrue($unlimitedClosure->accepted);
        self::assertNull($unlimitedClosure->limit);
    }
}
