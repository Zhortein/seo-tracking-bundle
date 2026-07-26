<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Statistics\Export;

use Zhortein\SeoTrackingBundle\Statistics\DataSource\StatisticsDataSourceInterface;
use Zhortein\SeoTrackingBundle\Statistics\DTO\HitObservation;
use Zhortein\SeoTrackingBundle\Statistics\Filter\StatisticsFilter;

final readonly class CsvStatisticsExporter implements CsvStatisticsExporterInterface
{
    /**
     * @var list<string>
     */
    private const array HEADERS = [
        'called_at',
        'bot',
        'closed',
        'duration_seconds',
        'page_url',
        'route',
        'source',
        'campaign',
        'medium',
        'page_type',
        'language',
        'dimensions',
    ];

    public function __construct(private StatisticsDataSourceInterface $dataSource)
    {
    }

    public function export(
        ?StatisticsFilter $filter = null,
        ?CsvExportOptions $options = null,
    ): iterable {
        $filter ??= new StatisticsFilter();
        $options ??= new CsvExportOptions();

        if ($options->includeUtf8Bom) {
            yield "\xEF\xBB\xBF";
        }

        yield $this->line(self::HEADERS, $options);

        foreach ($this->dataSource->observations($filter) as $observation) {
            yield $this->line($this->row($observation, $filter->timezone), $options);
        }
    }

    /**
     * @return list<string>
     */
    private function row(HitObservation $observation, \DateTimeZone $timezone): array
    {
        return [
            $observation->calledAt?->setTimezone($timezone)->format('Y-m-d\TH:i:s.uP') ?? '',
            $observation->bot ? 'true' : 'false',
            $observation->closed ? 'true' : 'false',
            null === $observation->durationSeconds ? '' : (string) $observation->durationSeconds,
            $this->spreadsheetSafe($observation->pageUrl),
            $this->spreadsheetSafe($observation->route),
            $this->spreadsheetSafe($observation->source),
            $this->spreadsheetSafe($observation->campaign),
            $this->spreadsheetSafe($observation->medium),
            $this->spreadsheetSafe($observation->pageType),
            $this->spreadsheetSafe($observation->language),
            $this->dimensions($observation->dimensions),
        ];
    }

    /**
     * @param array<string, string|int|float|bool> $dimensions
     */
    private function dimensions(array $dimensions): string
    {
        ksort($dimensions, SORT_STRING);

        return json_encode(
            $dimensions,
            JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );
    }

    private function spreadsheetSafe(?string $value): string
    {
        if (null === $value || '' === $value) {
            return '';
        }

        return preg_match('/^[=+\-@\t\r\n]/', $value) > 0 ? "'".$value : $value;
    }

    /**
     * @param list<string> $values
     */
    private function line(array $values, CsvExportOptions $options): string
    {
        $cells = array_map(
            static fn (string $value): string => $options->enclosure
                .str_replace($options->enclosure, $options->enclosure.$options->enclosure, $value)
                .$options->enclosure,
            $values,
        );

        return implode($options->delimiter, $cells).$options->lineEnding;
    }
}
