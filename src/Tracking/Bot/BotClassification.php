<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tracking\Bot;

final readonly class BotClassification
{
    public function __construct(
        public bool $bot,
        public ?string $classifier = null,
        public ?string $category = null,
        public ?string $identifier = null,
    ) {
    }

    public static function human(?string $classifier = null): self
    {
        return new self(false, $classifier);
    }

    public static function robot(
        ?string $classifier = null,
        ?string $category = null,
        ?string $identifier = null,
    ): self {
        return new self(true, $classifier, $category, $identifier);
    }
}
