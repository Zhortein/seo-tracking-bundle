<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tracking\RateLimit;

use Symfony\Component\HttpFoundation\Request;

final class ClientIpTrackingRateLimitKeyResolver implements TrackingRateLimitKeyResolverInterface
{
    public function resolve(Request $request, TrackingEndpoint $endpoint): string
    {
        return hash('sha256', $request->getClientIp() ?? 'unknown');
    }
}
