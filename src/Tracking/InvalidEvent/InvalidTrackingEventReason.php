<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tracking\InvalidEvent;

enum InvalidTrackingEventReason: string
{
    case MALFORMED_JSON = 'malformed_json';
    case UNSUPPORTED_PAYLOAD = 'unsupported_payload';
    case INVALID_PAYLOAD = 'invalid_payload';
    case CONSENT_DENIED = 'consent_denied';
    case RATE_LIMITED = 'rate_limited';
    case UNKNOWN_HIT = 'unknown_hit';
}
