<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tracking\RateLimit;

enum TrackingEndpoint: string
{
    case CREATION = 'creation';
    case CLOSURE = 'closure';
}
