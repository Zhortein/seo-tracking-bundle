<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tracking\RateLimit;

use Symfony\Component\HttpFoundation\Request;

final class AllowAllTrackingRateLimiter implements TrackingRateLimiterInterface
{
    public function consume(Request $request, TrackingEndpoint $endpoint): TrackingRateLimitDecision
    {
        return TrackingRateLimitDecision::accepted();
    }
}
