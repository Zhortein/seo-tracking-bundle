<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tracking\RateLimit;

use Symfony\Component\HttpFoundation\Request;

interface TrackingRateLimitKeyResolverInterface
{
    public function resolve(Request $request, TrackingEndpoint $endpoint): string;
}
