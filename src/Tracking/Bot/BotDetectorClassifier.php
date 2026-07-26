<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tracking\Bot;

final readonly class BotDetectorClassifier implements BotClassifierInterface
{
    public function __construct(private BotDetectorInterface $detector)
    {
    }

    public function classify(?string $userAgent): BotClassification
    {
        if ($this->detector instanceof BotClassifierInterface) {
            return $this->detector->classify($userAgent);
        }

        return $this->detector->isBot($userAgent)
            ? BotClassification::robot('legacy-detector', 'unknown')
            : BotClassification::human('legacy-detector');
    }
}
