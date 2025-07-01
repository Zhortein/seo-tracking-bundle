<?php

namespace Zhortein\SeoTrackingBundle\Event;

use ZhorTein\SeoTrackingBundle\Entity\PageCall;
use ZhorTein\SeoTrackingBundle\Entity\PageCallHit;
use Symfony\Contracts\EventDispatcher\Event;

final class PageCallTrackedEvent extends Event
{
    public function __construct(
        public readonly PageCall $pageCall,
        public readonly PageCallHit $pageCallHit
    ) {}
}