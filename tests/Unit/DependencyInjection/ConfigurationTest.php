<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Unit\DependencyInjection;

use PHPUnit\Framework\TestCase;
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
        $retention = $config['retention'];
        self::assertIsArray($retention);
        self::assertNull($retention['days']);
        self::assertSame(500, $retention['batch_size']);
        self::assertFalse($retention['remove_empty_page_calls']);
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
}
