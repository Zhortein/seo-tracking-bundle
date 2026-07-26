<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Journey\DTO;

final readonly class JourneyStep
{
    public function __construct(
        public int|string $hitId,
        public JourneyNode $node,
        public ?\DateTimeImmutable $calledAt,
        public bool $withinFilter,
    ) {
    }
}
