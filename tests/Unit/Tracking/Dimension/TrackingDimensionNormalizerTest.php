<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Unit\Tracking\Dimension;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zhortein\SeoTrackingBundle\Tracking\Dimension\TrackingDimensionNormalizer;

final class TrackingDimensionNormalizerTest extends TestCase
{
    public function testItAcceptsSortsAndPreservesBoundedScalarValues(): void
    {
        $dimensions = (new TrackingDimensionNormalizer())->normalize([
            'plan' => 'professional',
            'authenticated' => true,
            'score' => 4.5,
            'category_id' => 12,
        ]);

        self::assertSame([
            'authenticated' => true,
            'category_id' => 12,
            'plan' => 'professional',
            'score' => 4.5,
        ], $dimensions);
        self::assertNull((new TrackingDimensionNormalizer())->normalize(null));
        self::assertNull((new TrackingDimensionNormalizer())->normalize([]));
    }

    #[DataProvider('invalidDimensions')]
    public function testItRejectsUnsafeOrUnboundedValues(mixed $dimensions): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new TrackingDimensionNormalizer())->normalize($dimensions);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function invalidDimensions(): iterable
    {
        yield 'not an object' => ['tenant'];
        yield 'numeric key' => [['tenant']];
        yield 'invalid key' => [['tenant id' => 12]];
        yield 'nested object' => [['tenant' => ['id' => 12]]];
        yield 'null value' => [['tenant' => null]];
        yield 'blank value' => [['tenant' => '  ']];
        yield 'long value' => [['tenant' => str_repeat('a', TrackingDimensionNormalizer::MAX_STRING_LENGTH + 1)]];
        yield 'non finite float' => [['score' => INF]];
        yield 'encoded object too large' => [array_fill_keys(
            array_map(static fn (int $index): string => 'dimension_'.$index, range(1, TrackingDimensionNormalizer::MAX_DIMENSIONS)),
            str_repeat('a', TrackingDimensionNormalizer::MAX_STRING_LENGTH),
        )];
        yield 'too many entries' => [array_fill_keys(
            array_map(static fn (int $index): string => 'key_'.$index, range(1, TrackingDimensionNormalizer::MAX_DIMENSIONS + 1)),
            true,
        )];
    }
}
