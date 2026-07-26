# Streaming statistics exports

Version 1.7 provides a streaming CSV exporter over the same typed observations and filters used by reports and pagination. The bundle deliberately registers no public download route: the host application owns authorization, filename, audit logging, rate limits and response headers.

## CSV schema

The built-in exporter emits UTF-8 rows with this stable column order:

1. `called_at`
2. `bot`
3. `closed`
4. `duration_seconds`
5. `page_url`
6. `route`
7. `source`
8. `campaign`
9. `medium`
10. `page_type`
11. `language`
12. `dimensions`

Timestamps use the timezone from `StatisticsFilter` and include microseconds and an explicit UTC offset. Booleans are `true` or `false`; nullable values become empty cells. Dimensions are sorted by key and encoded as JSON with scalar types and float precision preserved.

The exporter never adds raw IP addresses, User-Agent values, visitor identifiers or any field absent from `HitObservation`.

## Stream a Symfony response

Inject `CsvStatisticsExporterInterface` and return a `StreamedResponse` from an application controller protected by the appropriate role:

```php
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Zhortein\SeoTrackingBundle\Statistics\Export\CsvExportOptions;
use Zhortein\SeoTrackingBundle\Statistics\Export\CsvStatisticsExporterInterface;
use Zhortein\SeoTrackingBundle\Statistics\Filter\StatisticsFilter;

final readonly class StatisticsExportController
{
    public function __construct(
        private CsvStatisticsExporterInterface $exporter,
    ) {
    }

    public function __invoke(): StreamedResponse
    {
        $filter = new StatisticsFilter(
            from: new \DateTimeImmutable('-30 days'),
            timezone: new \DateTimeZone('Europe/Paris'),
            bot: false,
            dimensions: ['tenant' => 'acme'],
        );
        $options = new CsvExportOptions(
            delimiter: ';',
            lineEnding: "\r\n",
            includeUtf8Bom: true,
        );

        $response = new StreamedResponse(function () use ($filter, $options): void {
            foreach ($this->exporter->export($filter, $options) as $chunk) {
                echo $chunk;
                flush();
            }
        });
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set(
            'Content-Disposition',
            $response->headers->makeDisposition(
                ResponseHeaderBag::DISPOSITION_ATTACHMENT,
                'seo-statistics.csv',
            ),
        );

        return $response;
    }
}
```

The iterable is lazy. It yields the optional UTF-8 BOM, then the header, then one chunk per matching observation. Do not wrap it in `iterator_to_array()` in production, as that would defeat streaming.

## CSV dialect

`CsvExportOptions` validates:

- a one-byte delimiter;
- a different one-byte enclosure;
- LF or CRLF line endings;
- an optional UTF-8 BOM.

Every field is enclosed, and enclosure characters are doubled. The exporter also prefixes an apostrophe when a string cell starts with `=`, `+`, `-`, `@`, tab, carriage return or line feed. This prevents URLs, routes and UTM values controlled by an attacker from becoming formulas when the file is opened in common spreadsheet software.

Dimensions are encoded as one JSON cell. Formula-like strings nested inside that JSON cannot become spreadsheet formulas because the cell itself starts with `{`.

## Filters, privacy and authorization

CSV export applies the complete `StatisticsFilter` before producing rows:

- inclusive start/end instants;
- bot choice;
- page type;
- every exact, type-sensitive dimension.

Export access must be at least as restricted as the statistics dashboard. Business dimensions and URLs can expose confidential activity even without direct identity. Keep export retention, download logging and recipients consistent with the application's privacy and security policy.

Large exports read the configured observation data source once and keep memory bounded, but they still scan every matching row. Use a narrow date range and application rate limiting. High-volume systems can replace the data source or exporter with a database-native implementation.

## External analytics adapters

Replace the CSV service while preserving its public contract:

```yaml
services:
    Zhortein\SeoTrackingBundle\Statistics\Export\CsvStatisticsExporterInterface:
        alias: App\Analytics\WarehouseCsvExporter
```

For a non-CSV external system, inject `StatisticsDataSourceInterface` directly and consume its filtered `HitObservation` iterable. The application adapter remains responsible for retries, credentials, remote schemas, deletion semantics and avoiding identity inference.
