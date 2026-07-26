<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Statistics\DTO;

final readonly class HitObservation
{
    public function __construct(
        public ?\DateTimeImmutable $calledAt,
        public bool $bot,
        public bool $closed,
        public ?int $durationSeconds,
        public string $pageUrl,
        public ?string $route,
        public ?string $source,
        public ?string $campaign,
        public ?string $medium,
        public ?string $pageType,
        public ?string $language,
    ) {
    }
}
