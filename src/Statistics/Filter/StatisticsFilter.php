<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Statistics\Filter;

use Zhortein\SeoTrackingBundle\Tracking\Dimension\TrackingDimensionNormalizer;

final readonly class StatisticsFilter
{
    public \DateTimeZone $timezone;
    public ?string $pageType;

    /**
     * @var array<string, string|int|float|bool>
     */
    public array $dimensions;

    /**
     * @param array<string, string|int|float|bool>|null $dimensions
     */
    public function __construct(
        public ?\DateTimeImmutable $from = null,
        public ?\DateTimeImmutable $to = null,
        ?\DateTimeZone $timezone = null,
        public ?bool $bot = null,
        ?string $pageType = null,
        ?array $dimensions = null,
    ) {
        if (null !== $from && null !== $to && $from > $to) {
            throw new \InvalidArgumentException('The statistics start date must be before the end date.');
        }

        $this->timezone = $timezone ?? new \DateTimeZone('UTC');
        $this->pageType = null === $pageType || '' === trim($pageType) ? null : trim($pageType);
        $this->dimensions = (new TrackingDimensionNormalizer())->normalize($dimensions) ?? [];
    }
}
