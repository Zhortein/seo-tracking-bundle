<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Statistics\DataSource;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;
use Zhortein\SeoTrackingBundle\Entity\PageCallHitInterface;
use Zhortein\SeoTrackingBundle\Statistics\DTO\HitObservation;
use Zhortein\SeoTrackingBundle\Statistics\Filter\StatisticsFilter;
use Zhortein\SeoTrackingBundle\Tracking\Dimension\TrackingDimensionNormalizer;

final readonly class DoctrineStatisticsDataSource implements StatisticsDataSourceInterface
{
    private TrackingDimensionNormalizer $dimensionNormalizer;

    /**
     * @param class-string<PageCallHitInterface> $pageCallHitClass
     */
    public function __construct(
        private ManagerRegistry $registry,
        private string $pageCallHitClass,
        ?TrackingDimensionNormalizer $dimensionNormalizer = null,
    ) {
        $this->dimensionNormalizer = $dimensionNormalizer ?? new TrackingDimensionNormalizer();
    }

    public function observations(StatisticsFilter $filter): iterable
    {
        $manager = $this->registry->getManagerForClass($this->pageCallHitClass);
        if (!$manager instanceof EntityManagerInterface) {
            throw new \LogicException(sprintf('The statistics data source requires an ORM manager for %s.', $this->pageCallHitClass));
        }

        $queryBuilder = $manager->createQueryBuilder()
            ->select(
                'hit.calledAt AS calledAt',
                'hit.exitedAt AS exitedAt',
                'hit.durationSeconds AS durationSeconds',
                'hit.bot AS bot',
                'hit.pageType AS pageType',
                'hit.language AS language',
                'hit.dimensions AS dimensions',
                'pageCall.url AS pageUrl',
                'pageCall.route AS route',
                'pageCall.source AS source',
                'pageCall.campaign AS campaign',
                'pageCall.medium AS medium',
            )
            ->from($this->pageCallHitClass, 'hit')
            ->innerJoin('hit.pageCall', 'pageCall')
            ->orderBy('hit.calledAt', 'ASC');

        if (null !== $filter->from) {
            $queryBuilder
                ->andWhere('hit.calledAt >= :statisticsFrom')
                ->setParameter('statisticsFrom', $filter->from);
        }

        if (null !== $filter->to) {
            $queryBuilder
                ->andWhere('hit.calledAt <= :statisticsTo')
                ->setParameter('statisticsTo', $filter->to);
        }

        if (null !== $filter->bot) {
            $queryBuilder
                ->andWhere('hit.bot = :statisticsBot')
                ->setParameter('statisticsBot', $filter->bot);
        }

        if (null !== $filter->pageType) {
            $queryBuilder
                ->andWhere('hit.pageType = :statisticsPageType')
                ->setParameter('statisticsPageType', $filter->pageType);
        }

        foreach ($queryBuilder->getQuery()->toIterable([], Query::HYDRATE_ARRAY) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $dimensions = $this->dimensions($row['dimensions'] ?? null);
            if (!$this->matchesDimensions($dimensions, $filter->dimensions)) {
                continue;
            }

            yield new HitObservation(
                $this->date($row['calledAt'] ?? null),
                true === ($row['bot'] ?? null) || 1 === ($row['bot'] ?? null),
                $row['exitedAt'] instanceof \DateTimeInterface,
                $this->integer($row['durationSeconds'] ?? null),
                $this->string($row['pageUrl'] ?? null) ?? '',
                $this->string($row['route'] ?? null),
                $this->string($row['source'] ?? null),
                $this->string($row['campaign'] ?? null),
                $this->string($row['medium'] ?? null),
                $this->string($row['pageType'] ?? null),
                $this->string($row['language'] ?? null),
                $dimensions,
            );
        }
    }

    /**
     * @return array<string, string|int|float|bool>
     */
    private function dimensions(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $dimensions = [];
        foreach ($value as $name => $dimension) {
            if (count($dimensions) >= TrackingDimensionNormalizer::MAX_DIMENSIONS) {
                break;
            }

            if (!is_string($name)) {
                continue;
            }

            try {
                $normalized = $this->dimensionNormalizer->normalize([$name => $dimension]);
            } catch (\InvalidArgumentException) {
                continue;
            }

            if (null !== $normalized) {
                $dimensions[$name] = $normalized[$name];
            }
        }

        try {
            return $this->dimensionNormalizer->normalize($dimensions) ?? [];
        } catch (\InvalidArgumentException) {
            return [];
        }
    }

    /**
     * @param array<string, string|int|float|bool> $dimensions
     * @param array<string, string|int|float|bool> $expected
     */
    private function matchesDimensions(array $dimensions, array $expected): bool
    {
        foreach ($expected as $name => $value) {
            if (!array_key_exists($name, $dimensions) || $dimensions[$name] !== $value) {
                return false;
            }
        }

        return true;
    }

    private function date(mixed $value): ?\DateTimeImmutable
    {
        return $value instanceof \DateTimeInterface ? \DateTimeImmutable::createFromInterface($value) : null;
    }

    private function integer(mixed $value): ?int
    {
        return is_int($value) ? $value : null;
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && '' !== trim($value) ? $value : null;
    }
}
