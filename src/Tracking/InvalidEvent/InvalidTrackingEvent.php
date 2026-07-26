<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tracking\InvalidEvent;

use Zhortein\SeoTrackingBundle\Tracking\RateLimit\TrackingEndpoint;

final readonly class InvalidTrackingEvent
{
    public function __construct(
        public InvalidTrackingEventReason $reason,
        public TrackingEndpoint $endpoint,
        public \DateTimeImmutable $occurredAt,
        public string $method,
        public ?string $route,
        public ?string $contentType,
        public ?int $contentLength,
    ) {
    }
}
