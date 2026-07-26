<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tracking\RateLimit;

final readonly class TrackingRateLimitDecision
{
    public function __construct(
        public bool $accepted,
        public ?int $limit = null,
        public ?int $remainingTokens = null,
        public ?\DateTimeImmutable $retryAfter = null,
    ) {
    }

    public static function accepted(): self
    {
        return new self(true);
    }
}
