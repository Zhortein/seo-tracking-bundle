<?php

namespace Zhortein\SeoTrackingBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Zhortein\SeoTrackingBundle\Entity\PageCall;
use Zhortein\SeoTrackingBundle\Entity\PageCallHit;

final class PageCallExitEvent extends Event
{
    public function __construct(
        protected readonly PageCall $pageCall,
        protected readonly PageCallHit $pageCallHit,
    ) {
    }

    public function getPageCall(): PageCall
    {
        return $this->pageCall;
    }

    public function getPageCallHit(): PageCallHit
    {
        return $this->pageCallHit;
    }
}
