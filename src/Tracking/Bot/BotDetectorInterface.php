<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tracking\Bot;

interface BotDetectorInterface
{
    public function isBot(?string $userAgent): bool;
}
