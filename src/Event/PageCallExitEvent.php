<?php

namespace Zhortein\SeoTrackingBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Zhortein\SeoTrackingBundle\Entity\PageCallHitInterface;
use Zhortein\SeoTrackingBundle\Entity\PageCallInterface;

final class PageCallExitEvent extends Event
{
    public function __construct(
        protected readonly PageCallInterface $pageCall,
        protected readonly PageCallHitInterface $pageCallHit,
    ) {
    }

    public function getPageCall(): PageCallInterface
    {
        return $this->pageCall;
    }

    public function getPageCallHit(): PageCallHitInterface
    {
        return $this->pageCallHit;
    }
}
