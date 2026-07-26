<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Unit\Statistics\Pagination;

use PHPUnit\Framework\TestCase;
use Zhortein\SeoTrackingBundle\Statistics\DataSource\StatisticsDataSourceInterface;
use Zhortein\SeoTrackingBundle\Statistics\DTO\HitObservation;
use Zhortein\SeoTrackingBundle\Statistics\Filter\StatisticsFilter;
use Zhortein\SeoTrackingBundle\Statistics\Pagination\ObservationBrowser;
use Zhortein\SeoTrackingBundle\Statistics\Pagination\ObservationPage;
use Zhortein\SeoTrackingBundle\Statistics\Pagination\ObservationPageRequest;

final class ObservationBrowserTest extends TestCase
{
    public function testItReturnsBoundedPagesAndStopsAfterTheLookAheadItem(): void
    {
        $dataSource = new class implements StatisticsDataSourceInterface {
            public int $iterations = 0;

            public function observations(StatisticsFilter $filter): iterable
            {
                foreach (range(1, 10) as $index) {
                    ++$this->iterations;
                    yield ObservationBrowserTest::observation('/'.$index);
                }
            }
        };
        $browser = new ObservationBrowser($dataSource);

        $page = $browser->page(new StatisticsFilter(), new ObservationPageRequest(2, 3));

        self::assertSame(['/3', '/4', '/5'], array_column($page->items, 'pageUrl'));
        self::assertTrue($page->hasMore);
        self::assertSame(5, $page->nextOffset());
        self::assertSame(6, $dataSource->iterations);
    }

    public function testItReportsTheLastAndEmptyPagesWithoutAFalseNextOffset(): void
    {
        $dataSource = new class implements StatisticsDataSourceInterface {
            public function observations(StatisticsFilter $filter): iterable
            {
                yield ObservationBrowserTest::observation('/1');
                yield ObservationBrowserTest::observation('/2');
            }
        };
        $browser = new ObservationBrowser($dataSource);

        $last = $browser->page(request: new ObservationPageRequest(1, 10));
        $empty = $browser->page(request: new ObservationPageRequest(2, 10));

        self::assertSame(['/2'], array_column($last->items, 'pageUrl'));
        self::assertFalse($last->hasMore);
        self::assertNull($last->nextOffset());
        self::assertSame([], $empty->items);
        self::assertFalse($empty->hasMore);
        self::assertNull($empty->nextOffset());
    }

    public function testPageBoundsAreValidated(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ObservationPageRequest(-1);
    }

    public function testPageSizeIsValidated(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ObservationPageRequest(limit: ObservationPageRequest::MAX_LIMIT + 1);
    }

    public function testAResultCannotContainMoreThanItsDeclaredLimit(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new ObservationPage([self::observation('/1'), self::observation('/2')], 0, 1, false);
    }

    public static function observation(string $url): HitObservation
    {
        return new HitObservation(
            new \DateTimeImmutable('2026-07-01 10:00:00 UTC'),
            false,
            true,
            10,
            $url,
            null,
            null,
            null,
            null,
            null,
            null,
        );
    }
}
