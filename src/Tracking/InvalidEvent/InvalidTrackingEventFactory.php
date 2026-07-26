<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tracking\InvalidEvent;

use Symfony\Component\HttpFoundation\Request;
use Zhortein\SeoTrackingBundle\Tracking\RateLimit\TrackingEndpoint;

final readonly class InvalidTrackingEventFactory
{
    private const MAX_METHOD_LENGTH = 16;
    private const MAX_ROUTE_LENGTH = 128;
    private const MAX_CONTENT_TYPE_LENGTH = 128;
    private const MAX_REPORTED_CONTENT_LENGTH = 10_000_000;

    public function create(
        Request $request,
        TrackingEndpoint $endpoint,
        InvalidTrackingEventReason $reason,
    ): InvalidTrackingEvent {
        $route = $request->attributes->get('_route');

        return new InvalidTrackingEvent(
            $reason,
            $endpoint,
            new \DateTimeImmutable(),
            mb_substr($request->getMethod(), 0, self::MAX_METHOD_LENGTH),
            is_string($route) ? mb_substr($route, 0, self::MAX_ROUTE_LENGTH) : null,
            $this->truncate($request->headers->get('Content-Type'), self::MAX_CONTENT_TYPE_LENGTH),
            $this->contentLength($request),
        );
    }

    private function truncate(?string $value, int $length): ?string
    {
        return null === $value ? null : mb_substr($value, 0, $length);
    }

    private function contentLength(Request $request): ?int
    {
        $value = $request->headers->get('Content-Length');
        if (null === $value || 1 !== preg_match('/^\d{1,10}$/', $value)) {
            return null;
        }

        return min((int) $value, self::MAX_REPORTED_CONTENT_LENGTH);
    }
}
