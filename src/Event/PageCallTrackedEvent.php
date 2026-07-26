<?php

namespace Zhortein\SeoTrackingBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;
use Zhortein\SeoTrackingBundle\Entity\PageCallHitInterface;
use Zhortein\SeoTrackingBundle\Entity\PageCallInterface;
use Zhortein\SeoTrackingBundle\Tracking\Bot\BotClassification;

final class PageCallTrackedEvent extends Event
{
    public function __construct(
        protected readonly PageCallInterface $pageCall,
        protected readonly PageCallHitInterface $pageCallHit,
        protected readonly ?BotClassification $botClassification = null,
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

    public function getBotClassification(): ?BotClassification
    {
        return $this->botClassification;
    }
}
