<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Unit\Tracking\Grouping;

use PHPUnit\Framework\TestCase;
use Zhortein\SeoTrackingBundle\Tracking\Grouping\PageCallGroupingKeyGenerator;

final class PageCallGroupingKeyGeneratorTest extends TestCase
{
    public function testItProducesAStableKeyAndTreatsNullAsARealGroupingValue(): void
    {
        $generator = new PageCallGroupingKeyGenerator();
        $utm = [
            'campaign' => null,
            'medium' => null,
            'source' => null,
            'term' => null,
            'content' => null,
        ];

        $first = $generator->generate('https://example.test/page', $utm, false);

        self::assertSame($first, $generator->generate('https://example.test/page', $utm, false));
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $first);
        self::assertNotSame($first, $generator->generate('https://example.test/page', [
            ...$utm,
            'campaign' => '',
        ], false));
        self::assertNotSame($first, $generator->generate('https://example.test/page', $utm, true));
    }
}
