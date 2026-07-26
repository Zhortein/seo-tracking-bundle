<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Fixtures;

use Zhortein\SeoTrackingBundle\Tracking\Bot\BotClassification;
use Zhortein\SeoTrackingBundle\Tracking\Bot\BotClassifierInterface;

final class AlwaysRobotBotClassifier implements BotClassifierInterface
{
    public function classify(?string $userAgent): BotClassification
    {
        return BotClassification::robot('test-classifier', 'automation', 'forced-test');
    }
}
