<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Unit\Statistics\Export;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zhortein\SeoTrackingBundle\Statistics\DataSource\StatisticsDataSourceInterface;
use Zhortein\SeoTrackingBundle\Statistics\DTO\HitObservation;
use Zhortein\SeoTrackingBundle\Statistics\Export\CsvExportOptions;
use Zhortein\SeoTrackingBundle\Statistics\Export\CsvStatisticsExporter;
use Zhortein\SeoTrackingBundle\Statistics\Filter\StatisticsFilter;

final class CsvStatisticsExporterTest extends TestCase
{
    public function testItExportsAStableEscapedAndSpreadsheetSafeSchema(): void
    {
        $exporter = new CsvStatisticsExporter($this->dataSource([
            new HitObservation(
                new \DateTimeImmutable('2026-07-01 22:30:00 UTC'),
                false,
                true,
                12,
                'https://example.test/a,one',
                'article"show',
                '=WEBSERVICE("https://evil.test")',
                'été',
                'cpc',
                'article',
                'fr',
                ['version' => 1.0, 'tenant' => 'acme'],
            ),
        ]));
        $filter = new StatisticsFilter(timezone: new \DateTimeZone('Europe/Paris'));

        $csv = implode('', iterator_to_array($exporter->export($filter), false));

        self::assertStringStartsWith(
            '"called_at","bot","closed","duration_seconds","page_url","route","source","campaign","medium","page_type","language","dimensions"'."\n",
            $csv,
        );
        self::assertStringContainsString('"2026-07-02T00:30:00.000000+02:00"', $csv);
        self::assertStringContainsString('"https://example.test/a,one"', $csv);
        self::assertStringContainsString('"article""show"', $csv);
        self::assertStringContainsString('"\'=WEBSERVICE(""https://evil.test"")"', $csv);
        self::assertStringContainsString('"été"', $csv);
        self::assertStringContainsString('"{""tenant"":""acme"",""version"":1.0}"', $csv);
        self::assertSame(2, substr_count($csv, "\n"));
    }

    public function testOptionsControlDelimiterEnclosureLineEndingAndBom(): void
    {
        $exporter = new CsvStatisticsExporter($this->dataSource([]));
        $options = new CsvExportOptions(';', "'", "\r\n", true);

        $chunks = iterator_to_array($exporter->export(options: $options), false);

        self::assertSame("\xEF\xBB\xBF", $chunks[0]);
        self::assertSame(
            "'called_at';'bot';'closed';'duration_seconds';'page_url';'route';'source';'campaign';'medium';'page_type';'language';'dimensions'\r\n",
            $chunks[1],
        );
    }

    public function testExportRemainsLazyAndDoesNotMaterializeTheObservationStream(): void
    {
        $dataSource = new class implements StatisticsDataSourceInterface {
            public int $iterations = 0;

            public function observations(StatisticsFilter $filter): iterable
            {
                foreach (range(1, 100) as $index) {
                    ++$this->iterations;
                    yield CsvStatisticsExporterTest::observation('/'.$index);
                }
            }
        };
        $stream = (new CsvStatisticsExporter($dataSource))->export();
        self::assertSame(0, $dataSource->iterations);

        foreach ($stream as $index => $chunk) {
            self::assertNotSame('', $chunk);
            if (1 === $index) {
                break;
            }
        }

        self::assertSame(1, $dataSource->iterations);
    }

    /**
     * @return iterable<string, array{string, string, string}>
     */
    public static function invalidOptions(): iterable
    {
        yield 'multi-byte delimiter' => ['::', '"', "\n"];
        yield 'line-break delimiter' => ["\n", '"', "\n"];
        yield 'multi-byte enclosure' => [',', '""', "\n"];
        yield 'same delimiter and enclosure' => [';', ';', "\n"];
        yield 'invalid line ending' => [',', '"', "\r"];
    }

    #[DataProvider('invalidOptions')]
    public function testOptionsRejectAmbiguousCsvDialects(
        string $delimiter,
        string $enclosure,
        string $lineEnding,
    ): void {
        $this->expectException(\InvalidArgumentException::class);

        new CsvExportOptions($delimiter, $enclosure, $lineEnding);
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

    /**
     * @param list<HitObservation> $observations
     */
    private function dataSource(array $observations): StatisticsDataSourceInterface
    {
        return new class($observations) implements StatisticsDataSourceInterface {
            /**
             * @param list<HitObservation> $observations
             */
            public function __construct(private readonly array $observations)
            {
            }

            public function observations(StatisticsFilter $filter): iterable
            {
                yield from $this->observations;
            }
        };
    }
}
