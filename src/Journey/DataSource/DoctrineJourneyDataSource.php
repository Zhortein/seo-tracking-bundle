<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Journey\DataSource;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\Persistence\ManagerRegistry;
use Zhortein\SeoTrackingBundle\Entity\PageCallHitInterface;
use Zhortein\SeoTrackingBundle\Journey\DTO\JourneyHitObservation;
use Zhortein\SeoTrackingBundle\Journey\DTO\JourneyNode;
use Zhortein\SeoTrackingBundle\Journey\Filter\JourneyFilter;

final readonly class DoctrineJourneyDataSource implements JourneyDataSourceInterface
{
    /**
     * @param class-string<PageCallHitInterface> $pageCallHitClass
     */
    public function __construct(
        private ManagerRegistry $registry,
        private string $pageCallHitClass,
    ) {
    }

    public function observations(JourneyFilter $filter): iterable
    {
        $manager = $this->registry->getManagerForClass($this->pageCallHitClass);
        if (!$manager instanceof EntityManagerInterface) {
            throw new \LogicException(sprintf('The journey data source requires an ORM manager for %s.', $this->pageCallHitClass));
        }

        $metadata = $manager->getClassMetadata($this->pageCallHitClass);
        if (!$metadata->isIdentifierComposite) {
            $identifier = 'hit.'.$metadata->getSingleIdentifierFieldName();
        } else {
            throw new \LogicException('The default journey data source does not support composite hit identifiers.');
        }

        $queryBuilder = $manager->createQueryBuilder()
            ->select(
                $identifier.' AS hitId',
                'IDENTITY(hit.parentHit) AS parentHitId',
                'hit.calledAt AS calledAt',
                'hit.bot AS bot',
                'hit.url AS hitUrl',
                'hit.pageType AS pageType',
                'pageCall.url AS pageUrl',
                'pageCall.route AS route',
                'parent.calledAt AS parentCalledAt',
                'parent.url AS parentHitUrl',
                'parent.pageType AS parentPageType',
                'parentPageCall.url AS parentPageUrl',
                'parentPageCall.route AS parentRoute',
            )
            ->from($this->pageCallHitClass, 'hit')
            ->innerJoin('hit.pageCall', 'pageCall')
            ->leftJoin('hit.parentHit', 'parent')
            ->leftJoin('parent.pageCall', 'parentPageCall')
            ->orderBy('hit.calledAt', 'ASC')
            ->addOrderBy($identifier, 'ASC');

        if (null !== $filter->from) {
            $queryBuilder
                ->andWhere('hit.calledAt >= :journeyFrom')
                ->setParameter('journeyFrom', $filter->from);
        }

        if (null !== $filter->to) {
            $queryBuilder
                ->andWhere('hit.calledAt <= :journeyTo')
                ->setParameter('journeyTo', $filter->to);
        }

        if (null !== $filter->bot) {
            $queryBuilder
                ->andWhere('hit.bot = :journeyBot')
                ->setParameter('journeyBot', $filter->bot);
        }

        foreach ($queryBuilder->getQuery()->toIterable([], Query::HYDRATE_ARRAY) as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = $this->identifier($row['hitId'] ?? null);
            if (null === $id) {
                throw new \LogicException('Journey observations require a scalar hit identifier.');
            }

            $parentId = $this->identifier($row['parentHitId'] ?? null);
            $parentNode = null;
            if (null !== $parentId) {
                $parentNode = new JourneyNode(
                    $this->string($row['parentHitUrl'] ?? null)
                        ?? $this->string($row['parentPageUrl'] ?? null)
                        ?? '',
                    $this->string($row['parentRoute'] ?? null),
                    $this->string($row['parentPageType'] ?? null),
                );
            }

            yield new JourneyHitObservation(
                $id,
                $parentId,
                $this->date($row['calledAt'] ?? null),
                true === ($row['bot'] ?? null) || 1 === ($row['bot'] ?? null),
                new JourneyNode(
                    $this->string($row['hitUrl'] ?? null)
                        ?? $this->string($row['pageUrl'] ?? null)
                        ?? '',
                    $this->string($row['route'] ?? null),
                    $this->string($row['pageType'] ?? null),
                ),
                $this->date($row['parentCalledAt'] ?? null),
                $parentNode,
            );
        }
    }

    private function date(mixed $value): ?\DateTimeImmutable
    {
        return $value instanceof \DateTimeInterface ? \DateTimeImmutable::createFromInterface($value) : null;
    }

    private function identifier(mixed $value): int|string|null
    {
        return is_int($value) || is_string($value) ? $value : null;
    }

    private function string(mixed $value): ?string
    {
        return is_string($value) && '' !== trim($value) ? $value : null;
    }
}
