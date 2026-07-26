<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Fixtures;

use Zhortein\SeoTrackingBundle\Tracking\InvalidEvent\InvalidTrackingEvent;
use Zhortein\SeoTrackingBundle\Tracking\InvalidEvent\InvalidTrackingEventReporterInterface;

final class CollectingInvalidTrackingEventReporter implements InvalidTrackingEventReporterInterface
{
    /**
     * @var list<InvalidTrackingEvent>
     */
    public array $events = [];

    public function report(InvalidTrackingEvent $event): void
    {
        $this->events[] = $event;
    }
}
