<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tracking\Consent;

use Symfony\Component\HttpFoundation\Request;

interface TrackingConsentCheckerInterface
{
    public function isGranted(?Request $request): bool;
}
