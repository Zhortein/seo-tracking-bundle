<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tracking\InvalidEvent;

interface InvalidTrackingEventReporterInterface
{
    public function report(InvalidTrackingEvent $event): void;
}
