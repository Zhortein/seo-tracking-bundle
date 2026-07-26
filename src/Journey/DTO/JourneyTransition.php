<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Journey\DTO;

final readonly class JourneyTransition
{
    public function __construct(
        public JourneyNode $from,
        public JourneyNode $to,
        public int $count,
    ) {
    }
}
