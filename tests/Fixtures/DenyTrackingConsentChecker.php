<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Fixtures;

use Symfony\Component\HttpFoundation\Request;
use Zhortein\SeoTrackingBundle\Tracking\Consent\TrackingConsentCheckerInterface;

final readonly class DenyTrackingConsentChecker implements TrackingConsentCheckerInterface
{
    public function isGranted(?Request $request): bool
    {
        return false;
    }
}
