<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tracking\Grouping;

final readonly class PageCallGroupingKeyGenerator implements PageCallGroupingKeyGeneratorInterface
{
    public function generate(string $url, array $utm, bool $bot): string
    {
        return hash('sha256', json_encode([
            'url' => $url,
            'campaign' => $utm['campaign'],
            'medium' => $utm['medium'],
            'source' => $utm['source'],
            'term' => $utm['term'],
            'content' => $utm['content'],
            'bot' => $bot,
        ], JSON_THROW_ON_ERROR));
    }
}
