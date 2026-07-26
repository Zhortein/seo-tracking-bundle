<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Journey\DTO;

use Zhortein\SeoTrackingBundle\Journey\Filter\JourneyFilter;

final readonly class JourneyReport
{
    /**
     * @param list<JourneyTransition> $topTransitions
     * @param list<JourneyPath>       $paths
     */
    public function __construct(
        public JourneyFilter $filter,
        public JourneySummary $summary,
        public array $topTransitions,
        public array $paths,
    ) {
    }
}
