<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Unit\Tracking\Bot;

use PHPUnit\Framework\TestCase;
use Zhortein\SeoTrackingBundle\Tracking\Bot\BotDetectorClassifier;
use Zhortein\SeoTrackingBundle\Tracking\Bot\BotDetectorInterface;
use Zhortein\SeoTrackingBundle\Tracking\Bot\RegexBotDetector;

final class BotDetectorClassifierTest extends TestCase
{
    public function testItPreservesRichClassificationFromModernDetectors(): void
    {
        $classification = (new BotDetectorClassifier(new RegexBotDetector()))
            ->classify('facebookexternalhit/1.1');

        self::assertTrue($classification->bot);
        self::assertSame('regex-default', $classification->classifier);
        self::assertSame('social_preview', $classification->category);
        self::assertSame('social-preview', $classification->identifier);
    }

    public function testItKeepsLegacyDetectorReplacementsWorking(): void
    {
        $legacyDetector = new class implements BotDetectorInterface {
            public function isBot(?string $userAgent): bool
            {
                return true;
            }
        };

        $classification = (new BotDetectorClassifier($legacyDetector))->classify('custom-agent');

        self::assertTrue($classification->bot);
        self::assertSame('legacy-detector', $classification->classifier);
        self::assertSame('unknown', $classification->category);
        self::assertNull($classification->identifier);
    }
}
