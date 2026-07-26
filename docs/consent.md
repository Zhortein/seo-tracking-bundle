# Consent integration

The bundle provides a generic integration point; it does not choose an application's legal basis, consent categories, cookie format or consent-management platform.

For backward compatibility, tracking is allowed immediately by the default `AllowAllTrackingConsentChecker`. Applications that need a consent gate replace `TrackingConsentCheckerInterface`.

## Server-side checker

The same checker is used:

- by `seo_tracking()` to render the controller's initial `consentGranted` value;
- by the tracking endpoint before it accepts a new hit.

An application can read its own consent cookie or session:

```php
<?php

namespace App\Analytics;

use Symfony\Component\HttpFoundation\Request;
use Zhortein\SeoTrackingBundle\Tracking\Consent\TrackingConsentCheckerInterface;

final readonly class AnalyticsConsentChecker implements TrackingConsentCheckerInterface
{
    public function isGranted(?Request $request): bool
    {
        return 'granted' === $request?->cookies->get('analytics_consent');
    }
}
```

```yaml
# config/services.yaml
services:
    App\Analytics\AnalyticsConsentChecker: ~

    Zhortein\SeoTrackingBundle\Tracking\Consent\TrackingConsentCheckerInterface:
        alias: App\Analytics\AnalyticsConsentChecker
```

When the checker returns false, no initial request is sent by the Twig-generated controller. A direct request to the tracking endpoint receives HTTP 403 and creates no entity. The exit endpoint remains available so revocation can close a hit that was validly opened earlier.

## Dynamic grant and revocation

The Stimulus controller listens on `document` for generic events:

```javascript
// Persist the consent state first so the server-side checker sees it.
document.dispatchEvent(new CustomEvent('seo-tracking:consent-granted'));

// Stops new tracking, closes the active hit once and clears the parent-hit link.
document.dispatchEvent(new CustomEvent('seo-tracking:consent-revoked'));
```

Repeated events are idempotent. Pending consent, revocation, Turbo navigation, visibility changes and Stimulus reconnection do not create duplicate listeners or hits. A new grant after revocation starts a fresh flow without linking it to the pre-revocation hit.

Event names can match an application's integration layer:

```yaml
# config/packages/zhortein_seo_tracking.yaml
zhortein_seo_tracking:
    consent:
        grant_event: 'cmp:analytics-granted'
        revoke_event: 'cmp:analytics-revoked'
```

The two names must be non-empty and distinct. The bundle deliberately does not listen to a vendor-specific event.

## Direct Stimulus usage

Applications not using `seo_tracking()` can provide the same values:

```twig
<body {{ stimulus_controller('zhortein--seo-tracking-bundle--tracking', {
    route: app.request.attributes.get('_route'),
    routeArgs: app.request.attributes.get('_route_params'),
    type: 'page',
    consentGranted: has_analytics_consent,
    consentGrantEvent: 'cmp:analytics-granted',
    consentRevokeEvent: 'cmp:analytics-revoked'
}) }}>
```

The server-side checker remains authoritative. A false client value can delay a request even when the default server checker allows it; a true client value cannot bypass a server checker that refuses it.

## Revocation and existing data

Revocation prevents new hits and closes the current one. It does not retroactively erase tracking data. The consuming application must define any erasure or shortened-retention requirement and can use the bundle's [retention workflow](retention.md) where appropriate.
