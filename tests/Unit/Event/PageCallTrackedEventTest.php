<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Unit\Event;

use PHPUnit\Framework\TestCase;
use Zhortein\SeoTrackingBundle\Entity\PageCall;
use Zhortein\SeoTrackingBundle\Entity\PageCallHit;
use Zhortein\SeoTrackingBundle\Event\PageCallTrackedEvent;
use Zhortein\SeoTrackingBundle\Tracking\Bot\BotClassification;

final class PageCallTrackedEventTest extends TestCase
{
    public function testItExposesTheOptionalClassificationWithoutBreakingOldConstruction(): void
    {
        $pageCall = new PageCall();
        $hit = new PageCallHit();
        $classification = BotClassification::robot('test', 'automation', 'fixture');

        $event = new PageCallTrackedEvent($pageCall, $hit, $classification);
        $legacyEvent = new PageCallTrackedEvent($pageCall, $hit);

        self::assertSame($classification, $event->getBotClassification());
        self::assertNull($legacyEvent->getBotClassification());
    }
}
