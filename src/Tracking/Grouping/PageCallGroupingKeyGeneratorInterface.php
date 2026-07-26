<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tracking\Grouping;

interface PageCallGroupingKeyGeneratorInterface
{
    /**
     * @param array{campaign: ?string, medium: ?string, source: ?string, term: ?string, content: ?string} $utm
     */
    public function generate(string $url, array $utm, bool $bot): string;
}
