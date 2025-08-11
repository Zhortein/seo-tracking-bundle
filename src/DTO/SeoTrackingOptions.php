<?php

namespace Zhortein\SeoTrackingBundle\DTO;

readonly class SeoTrackingOptions
{
    public function __construct(
        public ?string $pageCallEndpoint,
        public ?string $pageExitEndpoint,
        public ?string $apiKey,
        public bool $enable = false,
        public int $timeout = 300,
    ) {
    }
}
