<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tracking\Bot;

interface BotClassifierInterface
{
    public function classify(?string $userAgent): BotClassification;
}
