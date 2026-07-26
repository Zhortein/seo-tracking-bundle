<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Unit\Tracking\Request;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Zhortein\SeoTrackingBundle\Tracking\Request\TrackingPayloadFactory;
use Zhortein\SeoTrackingBundle\Tracking\Request\UnsupportedTrackingPayloadException;

final class TrackingPayloadFactoryTest extends TestCase
{
    public function testItAcceptsAClassicMinimalPayload(): void
    {
        $payload = (new TrackingPayloadFactory())->fromRequest(new Request(content: json_encode([
            'url' => 'https://example.test/current?utm_source=newsletter',
        ], JSON_THROW_ON_ERROR)));

        self::assertSame('https://example.test/current?utm_source=newsletter', $payload->url);
        self::assertSame($payload->url, $payload->groupingUrl());
        self::assertNull($payload->source);
        self::assertNull($payload->screenWidth);
        self::assertNull($payload->dimensions);
    }

    public function testCanonicalUrlBecomesTheGroupingUrlWithoutLosingTheObservedUrl(): void
    {
        $payload = (new TrackingPayloadFactory())->fromRequest(new Request(content: json_encode([
            'url' => 'https://example.test/current?variant=1',
            'canonicalUrl' => 'https://example.test/current',
            'screen' => ['width' => 1280, 'height' => 720],
            'dimensions' => [
                'plan' => 'professional',
                'authenticated' => true,
            ],
        ], JSON_THROW_ON_ERROR)));

        self::assertSame('https://example.test/current?variant=1', $payload->url);
        self::assertSame('https://example.test/current', $payload->groupingUrl());
        self::assertSame(1280, $payload->screenWidth);
        self::assertSame([
            'authenticated' => true,
            'plan' => 'professional',
        ], $payload->dimensions);
    }

    public function testItDistinguishesUnsupportedRootTypesFromInvalidObjectFields(): void
    {
        $this->expectException(UnsupportedTrackingPayloadException::class);

        (new TrackingPayloadFactory())->fromRequest(new Request(content: '[]'));
    }

    #[DataProvider('invalidPayloads')]
    public function testItRejectsMalformedOrInvalidPayloads(string $content): void
    {
        $this->expectException(\JsonException::class);
        if (json_validate($content)) {
            $this->expectException(\InvalidArgumentException::class);
        }

        (new TrackingPayloadFactory())->fromRequest(new Request(content: $content));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidPayloads(): iterable
    {
        yield 'malformed JSON' => ['{'];
        yield 'JSON list' => ['[]'];
        yield 'missing URL' => ['{}'];
        yield 'relative URL' => ['{"url":"/relative"}'];
        yield 'invalid screen' => ['{"url":"https://example.test","screen":"wide"}'];
        yield 'invalid optional type' => ['{"url":"https://example.test","campaign":12}'];
        yield 'nested dimensions' => ['{"url":"https://example.test","dimensions":{"tenant":{"id":12}}}'];
        yield 'dimension list' => ['{"url":"https://example.test","dimensions":["tenant"]}'];
    }
}
