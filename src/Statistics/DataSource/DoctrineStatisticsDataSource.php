<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Statistics\DataSource;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;
use Zhortein\SeoTrackingBundle\Entity\PageCallHitInterface;
use Zhortein\SeoTrackingBundle\Statistics\DTO\HitObservation;
use Zhortein\SeoTrackingBundle\Statistics\Filter\StatisticsFilter;

final readonly class DoctrineStatisticsDataSource implements StatisticsDataSourceInterface
{
    /**
     * @param class-string<PageCallHitInterface> $pageCallHitClass
     */
    public function __construct(
        private ManagerRegistry $registry,
        private string $pageCallHitClass,
    ) {
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
            );
        }
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
