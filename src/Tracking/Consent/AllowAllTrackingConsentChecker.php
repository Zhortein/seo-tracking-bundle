<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tracking\Consent;

use Symfony\Component\HttpFoundation\Request;

final readonly class AllowAllTrackingConsentChecker implements TrackingConsentCheckerInterface
{
    public function isGranted(?Request $request): bool
    {
        return true;
    }
}
