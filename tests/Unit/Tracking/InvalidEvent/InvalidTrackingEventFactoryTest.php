<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Unit\Tracking\InvalidEvent;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Zhortein\SeoTrackingBundle\Tracking\InvalidEvent\InvalidTrackingEventFactory;
use Zhortein\SeoTrackingBundle\Tracking\InvalidEvent\InvalidTrackingEventReason;
use Zhortein\SeoTrackingBundle\Tracking\RateLimit\TrackingEndpoint;

final class InvalidTrackingEventFactoryTest extends TestCase
{
    public function testItBuildsBoundedMetadataWithoutNetworkOrPayloadValues(): void
    {
        $secret = 'do-not-copy-this-value';
        $request = new Request(
            attributes: ['_route' => str_repeat('r', 200)],
            server: [
                'REQUEST_METHOD' => str_repeat('X', 24),
                'REMOTE_ADDR' => '203.0.113.42',
                'HTTP_USER_AGENT' => 'SecretAgent/1.0',
                'CONTENT_TYPE' => str_repeat('c', 200),
                'CONTENT_LENGTH' => (string) strlen($secret),
            ],
            content: $secret,
        );

        $event = (new InvalidTrackingEventFactory())->create(
            $request,
            TrackingEndpoint::CREATION,
            InvalidTrackingEventReason::INVALID_PAYLOAD,
        );

        self::assertSame(16, strlen($event->method));
        self::assertSame(128, strlen((string) $event->route));
        self::assertSame(128, strlen((string) $event->contentType));
        self::assertSame(strlen($secret), $event->contentLength);
        self::assertSame([
            'reason',
            'endpoint',
            'occurredAt',
            'method',
            'route',
            'contentType',
            'contentLength',
        ], array_keys(get_object_vars($event)));
        self::assertNotContains($secret, get_object_vars($event));
        self::assertNotContains('203.0.113.42', get_object_vars($event));
        self::assertNotContains('SecretAgent/1.0', get_object_vars($event));
    }

    public function testDeclaredContentLengthIsOptionalAndCapped(): void
    {
        $factory = new InvalidTrackingEventFactory();

        $missing = $factory->create(
            new Request(content: 'body-is-not-read-for-metadata'),
            TrackingEndpoint::CREATION,
            InvalidTrackingEventReason::RATE_LIMITED,
        );
        $oversized = $factory->create(
            new Request(server: ['CONTENT_LENGTH' => '9999999999']),
            TrackingEndpoint::CREATION,
            InvalidTrackingEventReason::RATE_LIMITED,
        );

        self::assertNull($missing->contentLength);
        self::assertSame(10_000_000, $oversized->contentLength);
    }
}
