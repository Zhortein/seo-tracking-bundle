<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tracking\InvalidEvent;

final class NullInvalidTrackingEventReporter implements InvalidTrackingEventReporterInterface
{
    public function report(InvalidTrackingEvent $event): void
    {
    }
}
