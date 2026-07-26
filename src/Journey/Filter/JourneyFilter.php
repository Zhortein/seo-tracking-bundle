<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Journey\Filter;

final readonly class JourneyFilter
{
    public function __construct(
        public ?\DateTimeImmutable $from = null,
        public ?\DateTimeImmutable $to = null,
        public ?bool $bot = null,
    ) {
        if (null !== $from && null !== $to && $from > $to) {
            throw new \InvalidArgumentException('The journey start date must be before the end date.');
        }
    }
}
