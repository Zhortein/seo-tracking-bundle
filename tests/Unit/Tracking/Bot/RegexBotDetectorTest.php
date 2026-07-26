<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Unit\Tracking\Bot;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zhortein\SeoTrackingBundle\Tracking\Bot\RegexBotDetector;

final class RegexBotDetectorTest extends TestCase
{
    /**
     * @return iterable<string, array{?string}>
     */
    public static function humanUserAgents(): iterable
    {
        yield 'missing' => [null];
        yield 'empty' => [''];
        yield 'whitespace' => ['  '];
        yield 'malformed bytes' => ["\0\1\2"];
        yield 'browser' => ['Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 Chrome/150.0'];
    }

    #[DataProvider('humanUserAgents')]
    public function testHumanAndUnusableUserAgentsRemainHuman(?string $userAgent): void
    {
        $detector = new RegexBotDetector();
        $classification = $detector->classify($userAgent);

        self::assertFalse($classification->bot);
        self::assertFalse($detector->isBot($userAgent));
        self::assertSame('regex-default', $classification->classifier);
        self::assertNull($classification->category);
        self::assertNull($classification->identifier);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function robotUserAgents(): iterable
    {
        yield 'search engine' => [
            'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
            'search_engine',
            'search-crawler',
        ];
        yield 'social preview' => [
            'facebookexternalhit/1.1 (+http://www.facebook.com/externalhit_uatext.php)',
            'social_preview',
            'social-preview',
        ];
        yield 'monitoring' => [
            'Mozilla/5.0 (compatible; UptimeRobot/2.0; http://www.uptimerobot.com/)',
            'monitoring',
            'uptime-monitor',
        ];
        yield 'generic crawler' => [
            'ExampleSpider/1.0',
            'crawler',
            'generic-crawler',
        ];
    }

    #[DataProvider('robotUserAgents')]
    public function testKnownRobotFamiliesAreExplainable(
        string $userAgent,
        string $expectedCategory,
        string $expectedIdentifier,
    ): void {
        $detector = new RegexBotDetector();
        $classification = $detector->classify($userAgent);

        self::assertTrue($classification->bot);
        self::assertTrue($detector->isBot($userAgent));
        self::assertSame('regex-default', $classification->classifier);
        self::assertSame($expectedCategory, $classification->category);
        self::assertSame($expectedIdentifier, $classification->identifier);
    }
}
