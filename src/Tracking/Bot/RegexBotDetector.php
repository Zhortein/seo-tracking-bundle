<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tracking\Bot;

final readonly class RegexBotDetector implements BotDetectorInterface
{
    public function isBot(?string $userAgent): bool
    {
        return null !== $userAgent
            && '' !== $userAgent
            && 1 === preg_match('/bot|crawl|slurp|spider/i', $userAgent);
    }
}
