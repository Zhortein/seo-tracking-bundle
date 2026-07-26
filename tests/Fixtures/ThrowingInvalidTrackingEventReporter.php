<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Fixtures;

use Zhortein\SeoTrackingBundle\Tracking\InvalidEvent\InvalidTrackingEvent;
use Zhortein\SeoTrackingBundle\Tracking\InvalidEvent\InvalidTrackingEventReporterInterface;

final class ThrowingInvalidTrackingEventReporter implements InvalidTrackingEventReporterInterface
{
    public function report(InvalidTrackingEvent $event): void
    {
        throw new \RuntimeException('Reporter failure must remain internal.');
    }
}
