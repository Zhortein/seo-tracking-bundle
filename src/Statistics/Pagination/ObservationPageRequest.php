<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Statistics\Pagination;

final readonly class ObservationPageRequest
{
    public const int DEFAULT_LIMIT = 50;
    public const int MAX_LIMIT = 500;

    public function __construct(
        public int $offset = 0,
        public int $limit = self::DEFAULT_LIMIT,
    ) {
        if ($offset < 0) {
            throw new \InvalidArgumentException('The statistics observation offset must be zero or greater.');
        }

        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw new \InvalidArgumentException(sprintf(
                'The statistics observation page size must be between 1 and %d.',
                self::MAX_LIMIT,
            ));
        }
    }
}
