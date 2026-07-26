<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Statistics;

use Zhortein\SeoTrackingBundle\Statistics\DataSource\StatisticsDataSourceInterface;
use Zhortein\SeoTrackingBundle\Statistics\DTO\DimensionRankedValue;
use Zhortein\SeoTrackingBundle\Statistics\DTO\DimensionRanking;
use Zhortein\SeoTrackingBundle\Statistics\DTO\HitObservation;
use Zhortein\SeoTrackingBundle\Statistics\DTO\RankedValue;
use Zhortein\SeoTrackingBundle\Statistics\DTO\StatisticsReport;
use Zhortein\SeoTrackingBundle\Statistics\DTO\StatisticsSummary;
use Zhortein\SeoTrackingBundle\Statistics\DTO\TrendPoint;
use Zhortein\SeoTrackingBundle\Statistics\Filter\StatisticsFilter;

final readonly class StatisticsProvider implements StatisticsProviderInterface
{
    public function __construct(private StatisticsDataSourceInterface $dataSource)
    {
    }

    public function report(?StatisticsFilter $filter = null, int $limit = 10): StatisticsReport
    {
        if ($limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException('The statistics ranking limit must be between 1 and 100.');
        }

        $filter ??= new StatisticsFilter();
        $pageCalls = 0;
        $humanHits = 0;
        $robotHits = 0;
        $closedHits = 0;
        $durations = [];
        $topPages = [];
        $sources = [];
        $campaigns = [];
        $mediums = [];
        $pageTypes = [];
        $routes = [];
        $languages = [];
        $trend = [];
        $dimensions = [];

        foreach ($this->dataSource->observations($filter) as $observation) {
            ++$pageCalls;
            if ($observation->bot) {
                ++$robotHits;
            } else {
                ++$humanHits;
            }
            if ($observation->closed) {
                ++$closedHits;
            }

            if ($observation->closed && null !== $observation->durationSeconds && $observation->durationSeconds >= 0) {
                $durations[] = $observation->durationSeconds;
            }

            $this->increment($topPages, $observation->pageUrl);
            $this->increment($sources, $observation->source);
            $this->increment($campaigns, $observation->campaign);
            $this->increment($mediums, $observation->medium);
            $this->increment($pageTypes, $observation->pageType);
            $this->increment($routes, $observation->route);
            $this->increment($languages, $observation->language);
            $this->incrementTrend($trend, $observation, $filter->timezone);
            $this->incrementDimensions($dimensions, $observation->dimensions);
        }

        sort($durations, SORT_NUMERIC);
        $durationSamples = count($durations);
        $average = 0 === $durationSamples ? null : array_sum($durations) / $durationSamples;
        $median = $this->median($durations);

        return new StatisticsReport(
            $filter,
            new StatisticsSummary(
                $pageCalls,
                $humanHits,
                $robotHits,
                $closedHits,
                $durationSamples,
                null === $average ? null : round($average, 2),
                $median,
            ),
            $this->rank($topPages, $limit),
            $this->rank($sources, $limit),
            $this->rank($campaigns, $limit),
            $this->rank($mediums, $limit),
            $this->rank($pageTypes, $limit),
            $this->rank($routes, $limit),
            $this->rank($languages, $limit),
            $this->trend($trend, $filter->timezone),
            $this->dimensionRankings($dimensions, $limit),
        );
    }

    /**
     * @param array<string, array<string, array{value: string|int|float|bool, count: int}>> $values
     * @param array<string, string|int|float|bool>                                          $dimensions
     */
    private function incrementDimensions(array &$values, array $dimensions): void
    {
        foreach ($dimensions as $name => $value) {
            if ('' === trim($name) || (is_float($value) && !is_finite($value))) {
                continue;
            }

            $key = $this->dimensionValueKey($value);
            $values[$name][$key] ??= ['value' => $value, 'count' => 0];
            ++$values[$name][$key]['count'];
        }
    }

    private function dimensionValueKey(string|int|float|bool $value): string
    {
        return match (true) {
            is_string($value) => 'string:'.$value,
            is_int($value) => 'integer:'.$value,
            is_float($value) => 'float:'.serialize($value),
            default => 'boolean:'.($value ? 'true' : 'false'),
        };
    }

    /**
     * @param array<string, array<string, array{value: string|int|float|bool, count: int}>> $dimensions
     *
     * @return list<DimensionRanking>
     */
    private function dimensionRankings(array $dimensions, int $limit): array
    {
        uksort($dimensions, static fn (string $left, string $right): int => strnatcasecmp($left, $right));
        $rankings = [];

        foreach ($dimensions as $name => $values) {
            uasort($values, static function (array $left, array $right): int {
                $byCount = $right['count'] <=> $left['count'];
                if (0 !== $byCount) {
                    return $byCount;
                }

                $byLabel = strnatcasecmp((string) $left['value'], (string) $right['value']);

                return 0 !== $byLabel ? $byLabel : strcmp(serialize($left['value']), serialize($right['value']));
            });

            $ranked = [];
            foreach (array_slice($values, 0, $limit, true) as $value) {
                $ranked[] = new DimensionRankedValue($value['value'], $value['count']);
            }
            $rankings[] = new DimensionRanking($name, $ranked);
        }

        return $rankings;
    }

    /**
     * @param array<string, int> $values
     */
    private function increment(array &$values, ?string $label): void
    {
        if (null === $label || '' === trim($label)) {
            return;
        }

        $values[$label] = ($values[$label] ?? 0) + 1;
    }

    /**
     * @param array<string, array{hits: int, human: int, robots: int}> $trend
     */
    private function incrementTrend(array &$trend, HitObservation $observation, \DateTimeZone $timezone): void
    {
        if (null === $observation->calledAt) {
            return;
        }

        $date = $observation->calledAt->setTimezone($timezone)->format('Y-m-d');
        $trend[$date] ??= ['hits' => 0, 'human' => 0, 'robots' => 0];
        ++$trend[$date]['hits'];
        ++$trend[$date][$observation->bot ? 'robots' : 'human'];
    }

    /**
     * @param array<string, int> $values
     *
     * @return list<RankedValue>
     */
    private function rank(array $values, int $limit): array
    {
        uksort($values, static function (string $left, string $right) use ($values): int {
            $byCount = $values[$right] <=> $values[$left];

            return 0 !== $byCount ? $byCount : strnatcasecmp($left, $right);
        });

        $ranked = [];
        foreach (array_slice($values, 0, $limit, true) as $label => $count) {
            $ranked[] = new RankedValue($label, $count);
        }

        return $ranked;
    }

    /**
     * @param list<int> $values
     */
    private function median(array $values): ?float
    {
        $count = count($values);
        if (0 === $count) {
            return null;
        }

        $middle = intdiv($count, 2);

        return 1 === $count % 2
            ? (float) $values[$middle]
            : ($values[$middle - 1] + $values[$middle]) / 2;
    }

    /**
     * @param array<string, array{hits: int, human: int, robots: int}> $values
     *
     * @return list<TrendPoint>
     */
    private function trend(array $values, \DateTimeZone $timezone): array
    {
        ksort($values);
        $trend = [];
        foreach ($values as $date => $counts) {
            $trend[] = new TrendPoint(
                new \DateTimeImmutable($date.' 00:00:00', $timezone),
                $counts['hits'],
                $counts['human'],
                $counts['robots'],
            );
        }

        return $trend;
    }
}
