<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tracking\Bot;

final readonly class RegexBotDetector implements BotClassifierInterface, BotDetectorInterface
{
    /**
     * @var list<array{pattern: string, category: string, identifier: string}>
     */
    private const RULES = [
        [
            'pattern' => '/Googlebot|bingbot|DuckDuckBot|Baiduspider|YandexBot|Applebot/i',
            'category' => 'search_engine',
            'identifier' => 'search-crawler',
        ],
        [
            'pattern' => '/facebookexternalhit|Twitterbot|LinkedInBot|Slackbot|Discordbot|WhatsApp/i',
            'category' => 'social_preview',
            'identifier' => 'social-preview',
        ],
        [
            'pattern' => '/UptimeRobot|Pingdom|StatusCake|Better Uptime/i',
            'category' => 'monitoring',
            'identifier' => 'uptime-monitor',
        ],
        [
            'pattern' => '/bot|crawl|slurp|spider/i',
            'category' => 'crawler',
            'identifier' => 'generic-crawler',
        ],
    ];

    public function isBot(?string $userAgent): bool
    {
        return $this->classify($userAgent)->bot;
    }

    public function classify(?string $userAgent): BotClassification
    {
        if (null === $userAgent || '' === trim($userAgent)) {
            return BotClassification::human('regex-default');
        }

        foreach (self::RULES as $rule) {
            if (1 === preg_match($rule['pattern'], $userAgent)) {
                return BotClassification::robot(
                    'regex-default',
                    $rule['category'],
                    $rule['identifier'],
                );
            }
        }

        return BotClassification::human('regex-default');
    }
}
