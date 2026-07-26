<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tracking\RateLimit;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

final class SymfonyTrackingRateLimiter implements TrackingRateLimiterInterface
{
    public function __construct(
        private readonly ?RateLimiterFactoryInterface $creationLimiter,
        private readonly ?RateLimiterFactoryInterface $closureLimiter,
        private readonly TrackingRateLimitKeyResolverInterface $keyResolver,
    ) {
    }

    public function consume(Request $request, TrackingEndpoint $endpoint): TrackingRateLimitDecision
    {
        $factory = match ($endpoint) {
            TrackingEndpoint::CREATION => $this->creationLimiter,
            TrackingEndpoint::CLOSURE => $this->closureLimiter,
        };

        if (null === $factory) {
            return TrackingRateLimitDecision::accepted();
        }

        $limit = $factory
            ->create($this->keyResolver->resolve($request, $endpoint))
            ->consume();

        return new TrackingRateLimitDecision(
            $limit->isAccepted(),
            $limit->getLimit(),
            $limit->getRemainingTokens(),
            $limit->getRetryAfter(),
        );
    }
}
