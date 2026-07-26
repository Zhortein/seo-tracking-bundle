<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Statistics\Filter;

final readonly class StatisticsFilter
{
    public \DateTimeZone $timezone;
    public ?string $pageType;

    public function __construct(
        public ?\DateTimeImmutable $from = null,
        public ?\DateTimeImmutable $to = null,
        ?\DateTimeZone $timezone = null,
        public ?bool $bot = null,
        ?string $pageType = null,
    ) {
        if (null !== $from && null !== $to && $from > $to) {
            throw new \InvalidArgumentException('The statistics start date must be before the end date.');
        }

        $this->timezone = $timezone ?? new \DateTimeZone('UTC');
        $this->pageType = null === $pageType || '' === trim($pageType) ? null : trim($pageType);
    }
}
