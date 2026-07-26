<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Zhortein\SeoTrackingBundle\DependencyInjection\Configuration;
use Zhortein\SeoTrackingBundle\Entity\PageCall;
use Zhortein\SeoTrackingBundle\Entity\PageCallHit;

final class ConfigurationTest extends TestCase
{
    public function testDefaultsRemainBackwardCompatible(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), []);

        self::assertSame(PageCall::class, $config['page_call_class']);
        self::assertSame(PageCallHit::class, $config['page_call_hit_class']);
        self::assertFalse($config['easylyse_enabled']);
        self::assertSame('', $config['easylyse_api_key']);
        self::assertSame(300, $config['easylyse_timeout']);
        $anonymization = $config['anonymization'];
        self::assertIsArray($anonymization);
        self::assertSame(24, $anonymization['ipv4_prefix']);
        self::assertSame(64, $anonymization['ipv6_prefix']);
        self::assertNull($config['tracking_url']);
        self::assertNull($config['exit_url']);
        $statistics = $config['statistics'];
        self::assertIsArray($statistics);
        self::assertSame('bootstrap5', $statistics['theme']);
        self::assertNull($statistics['template']);
        $statisticsCache = $statistics['cache'];
        self::assertIsArray($statisticsCache);
        self::assertNull($statisticsCache['pool']);
        self::assertSame(0, $statisticsCache['ttl']);
        $retention = $config['retention'];
        self::assertIsArray($retention);
        self::assertNull($retention['days']);
        self::assertSame(500, $retention['batch_size']);
        self::assertFalse($retention['remove_empty_page_calls']);
        $consent = $config['consent'];
        self::assertIsArray($consent);
        self::assertSame('seo-tracking:consent-granted', $consent['grant_event']);
        self::assertSame('seo-tracking:consent-revoked', $consent['revoke_event']);
        $rateLimiter = $config['rate_limiter'];
        self::assertIsArray($rateLimiter);
        self::assertNull($rateLimiter['creation_limiter']);
        self::assertNull($rateLimiter['closure_limiter']);
    }

    public function testRetentionPolicyCanBeConfigured(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'retention' => [
                'days' => 180,
                'batch_size' => 250,
                'remove_empty_page_calls' => true,
            ],
        ]]);

        $retention = $config['retention'];
        self::assertIsArray($retention);
        self::assertSame(180, $retention['days']);
        self::assertSame(250, $retention['batch_size']);
        self::assertTrue($retention['remove_empty_page_calls']);
    }

    public function testConsentEventsCanBeConfigured(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'consent' => [
                'grant_event' => 'cmp:analytics-granted',
                'revoke_event' => 'cmp:analytics-revoked',
            ],
        ]]);

        $consent = $config['consent'];
        self::assertIsArray($consent);
        self::assertSame('cmp:analytics-granted', $consent['grant_event']);
        self::assertSame('cmp:analytics-revoked', $consent['revoke_event']);
    }

    public function testRateLimiterServicesCanBeConfiguredIndependently(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'rate_limiter' => [
                'creation_limiter' => 'limiter.seo_tracking_creation',
                'closure_limiter' => 'limiter.seo_tracking_closure',
            ],
        ]]);

        $rateLimiter = $config['rate_limiter'];
        self::assertIsArray($rateLimiter);
        self::assertSame('limiter.seo_tracking_creation', $rateLimiter['creation_limiter']);
        self::assertSame('limiter.seo_tracking_closure', $rateLimiter['closure_limiter']);
    }

    public function testStatisticsCacheCanBeConfiguredExplicitly(): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'statistics' => [
                'cache' => [
                    'pool' => 'cache.seo_tracking_statistics',
                    'ttl' => 120,
                ],
            ],
        ]]);

        $statistics = $config['statistics'];
        self::assertIsArray($statistics);
        $cache = $statistics['cache'];
        self::assertIsArray($cache);
        self::assertSame('cache.seo_tracking_statistics', $cache['pool']);
        self::assertSame(120, $cache['ttl']);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function statisticsThemes(): iterable
    {
        yield 'Bootstrap 5' => ['bootstrap5'];
        yield 'framework-neutral HTML5' => ['html5'];
        yield 'no bundled theme' => ['none'];
    }

    #[DataProvider('statisticsThemes')]
    public function testEveryStatisticsThemeCanBeSelected(string $theme): void
    {
        $config = (new Processor())->processConfiguration(new Configuration(), [[
            'statistics' => ['theme' => $theme],
        ]]);

        $statistics = $config['statistics'];
        self::assertIsArray($statistics);
        self::assertSame($theme, $statistics['theme']);
    }

    public function testStatisticsCachePoolCannotBeEmpty(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(), [[
            'statistics' => [
                'cache' => [
                    'pool' => ' ',
                    'ttl' => 60,
                ],
            ],
        ]]);
    }

    public function testStatisticsCachePoolRequiresAPositiveTtl(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(), [[
            'statistics' => [
                'cache' => [
                    'pool' => 'cache.seo_tracking_statistics',
                ],
            ],
        ]]);
    }

    public function testStatisticsCacheTtlRequiresAPool(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(), [[
            'statistics' => [
                'cache' => [
                    'ttl' => 60,
                ],
            ],
        ]]);
    }

    public function testRateLimiterServiceIdsCannotBeEmpty(): void
    {
        $this->expectException(InvalidConfigurationException::class);

        (new Processor())->processConfiguration(new Configuration(), [[
            'rate_limiter' => [
                'creation_limiter' => ' ',
            ],
        ]]);
    }
}
