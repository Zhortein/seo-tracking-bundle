<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Journey\DTO;

final readonly class JourneyHitObservation
{
    public function __construct(
        public int|string $id,
        public int|string|null $parentId,
        public ?\DateTimeImmutable $calledAt,
        public bool $bot,
        public JourneyNode $node,
        public ?\DateTimeImmutable $parentCalledAt = null,
        public ?JourneyNode $parentNode = null,
    ) {
    }
}
