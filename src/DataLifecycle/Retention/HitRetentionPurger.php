<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\DataLifecycle\Retention;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ManagerRegistry;
use Zhortein\SeoTrackingBundle\Entity\PageCallHitInterface;
use Zhortein\SeoTrackingBundle\Entity\PageCallInterface;

final readonly class HitRetentionPurger implements HitRetentionPurgerInterface
{
    /**
     * @param class-string<PageCallInterface>    $pageCallClass
     * @param class-string<PageCallHitInterface> $pageCallHitClass
     */
    public function __construct(
        private ManagerRegistry $managerRegistry,
        private string $pageCallClass,
        private string $pageCallHitClass,
    ) {
    }

    public function purge(RetentionPurgeOptions $options): RetentionPurgeResult
    {
        $entityManager = $this->managerRegistry->getManagerForClass($this->pageCallHitClass);
        if (!$entityManager instanceof EntityManagerInterface) {
            throw new \LogicException(sprintf('No Doctrine ORM entity manager manages %s.', $this->pageCallHitClass));
        }

        if ($entityManager !== $this->managerRegistry->getManagerForClass($this->pageCallClass)) {
            throw new \LogicException('Configured tracking hits and page calls must use the same Doctrine ORM entity manager.');
        }

        /** @var ClassMetadata<PageCallHitInterface> $hitMetadata */
        $hitMetadata = $entityManager->getClassMetadata($this->pageCallHitClass);
        /** @var ClassMetadata<PageCallInterface> $pageCallMetadata */
        $pageCallMetadata = $entityManager->getClassMetadata($this->pageCallClass);
        $this->validateMetadata($hitMetadata, $pageCallMetadata);

        $candidateHits = $this->countCandidates($entityManager, $options->cutoff);
        $affectedPageCalls = $this->countAffectedPageCalls($entityManager, $pageCallMetadata, $options->cutoff);
        $undatedHits = $this->countUndatedHits($entityManager);

        if (!$options->apply || 0 === $candidateHits) {
            return new RetentionPurgeResult(
                $options->cutoff,
                $candidateHits,
                0,
                $affectedPageCalls,
                0,
                $undatedHits,
                false,
            );
        }

        $purgedHits = 0;
        $removedPageCalls = 0;
        $hitIdentifier = $hitMetadata->getSingleIdentifierFieldName();
        $pageCallIdentifier = $pageCallMetadata->getSingleIdentifierFieldName();

        while ([] !== $rows = $this->candidateRows(
            $entityManager,
            $hitIdentifier,
            $pageCallIdentifier,
            $options->cutoff,
            $options->batchSize,
        )) {
            $hitIds = array_column($rows, 'hitId');
            $pageCallIds = array_values(array_unique(array_column($rows, 'pageCallId'), SORT_REGULAR));

            $connection = $entityManager->getConnection();
            $connection->beginTransaction();
            try {
                $entityManager->createQueryBuilder()
                    ->update($this->pageCallHitClass, 'child')
                    ->set('child.parentHit', 'NULL')
                    ->where('IDENTITY(child.parentHit) IN (:hitIds)')
                    ->setParameter('hitIds', $hitIds)
                    ->getQuery()
                    ->execute();

                $deleteResult = $entityManager->createQueryBuilder()
                    ->delete($this->pageCallHitClass, 'hit')
                    ->where(sprintf('hit.%s IN (:hitIds)', $hitIdentifier))
                    ->setParameter('hitIds', $hitIds)
                    ->getQuery()
                    ->execute();
                if (!is_int($deleteResult)) {
                    throw new \LogicException('Doctrine did not return an affected-row count for the retention deletion.');
                }
                $batchPurgedHits = $deleteResult;
                $batchRemovedPageCalls = 0;

                foreach ($pageCallIds as $pageCallId) {
                    $pageCall = $entityManager->getRepository($this->pageCallClass)->find($pageCallId);
                    if (!$pageCall instanceof PageCallInterface) {
                        continue;
                    }

                    if ($this->refreshPageCall($entityManager, $pageCallMetadata, $hitMetadata, $pageCall)) {
                        continue;
                    }

                    if ($options->removeEmptyPageCalls) {
                        $entityManager->remove($pageCall);
                        ++$batchRemovedPageCalls;
                    }
                }

                $entityManager->flush();
                $connection->commit();
                $purgedHits += $batchPurgedHits;
                $removedPageCalls += $batchRemovedPageCalls;
            } catch (\Throwable $exception) {
                if ($connection->isTransactionActive()) {
                    $connection->rollBack();
                }
                $entityManager->clear();

                throw $exception;
            }

            $entityManager->clear();
        }

        return new RetentionPurgeResult(
            $options->cutoff,
            $candidateHits,
            $purgedHits,
            $affectedPageCalls,
            $removedPageCalls,
            $undatedHits,
            true,
        );
    }

    private function countCandidates(EntityManagerInterface $entityManager, \DateTimeImmutable $cutoff): int
    {
        return (int) $entityManager->createQueryBuilder()
            ->select('COUNT(hit)')
            ->from($this->pageCallHitClass, 'hit')
            ->where('hit.calledAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @param ClassMetadata<PageCallInterface> $pageCallMetadata
     */
    private function countAffectedPageCalls(
        EntityManagerInterface $entityManager,
        ClassMetadata $pageCallMetadata,
        \DateTimeImmutable $cutoff,
    ): int {
        $identifier = $pageCallMetadata->getSingleIdentifierFieldName();

        return (int) $entityManager->createQueryBuilder()
            ->select(sprintf('COUNT(DISTINCT pageCall.%s)', $identifier))
            ->from($this->pageCallHitClass, 'hit')
            ->innerJoin('hit.pageCall', 'pageCall')
            ->where('hit.calledAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function countUndatedHits(EntityManagerInterface $entityManager): int
    {
        return (int) $entityManager->createQueryBuilder()
            ->select('COUNT(hit)')
            ->from($this->pageCallHitClass, 'hit')
            ->where('hit.calledAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<array{hitId: mixed, pageCallId: mixed}>
     */
    private function candidateRows(
        EntityManagerInterface $entityManager,
        string $hitIdentifier,
        string $pageCallIdentifier,
        \DateTimeImmutable $cutoff,
        int $batchSize,
    ): array {
        /** @var list<array{hitId: mixed, pageCallId: mixed}> $rows */
        $rows = $entityManager->createQueryBuilder()
            ->select(sprintf('hit.%s AS hitId', $hitIdentifier))
            ->addSelect(sprintf('pageCall.%s AS pageCallId', $pageCallIdentifier))
            ->from($this->pageCallHitClass, 'hit')
            ->innerJoin('hit.pageCall', 'pageCall')
            ->where('hit.calledAt < :cutoff')
            ->orderBy(sprintf('hit.%s', $hitIdentifier), 'ASC')
            ->setParameter('cutoff', $cutoff)
            ->setMaxResults($batchSize)
            ->getQuery()
            ->getArrayResult();

        return $rows;
    }

    /**
     * @param ClassMetadata<PageCallInterface>    $pageCallMetadata
     * @param ClassMetadata<PageCallHitInterface> $hitMetadata
     */
    private function refreshPageCall(
        EntityManagerInterface $entityManager,
        ClassMetadata $pageCallMetadata,
        ClassMetadata $hitMetadata,
        PageCallInterface $pageCall,
    ): bool {
        $remainingHits = (int) $entityManager->createQueryBuilder()
            ->select('COUNT(hit)')
            ->from($this->pageCallHitClass, 'hit')
            ->where('hit.pageCall = :pageCall')
            ->setParameter('pageCall', $pageCall)
            ->getQuery()
            ->getSingleScalarResult();

        $pageCallMetadata->setFieldValue($pageCall, 'nbCalls', $remainingHits);
        if (0 === $remainingHits) {
            $pageCallMetadata->setFieldValue($pageCall, 'lastCalledAt', null);

            return false;
        }

        $firstHit = $this->datedBoundaryHit($entityManager, 'ASC', $pageCall);
        $lastHit = $this->datedBoundaryHit($entityManager, 'DESC', $pageCall);
        $firstCalledAt = $firstHit instanceof PageCallHitInterface
            ? $hitMetadata->getFieldValue($firstHit, 'calledAt')
            : null;
        $lastCalledAt = $lastHit instanceof PageCallHitInterface
            ? $hitMetadata->getFieldValue($lastHit, 'calledAt')
            : null;

        if ($firstCalledAt instanceof \DateTimeInterface) {
            $pageCallMetadata->setFieldValue($pageCall, 'firstCalledAt', \DateTimeImmutable::createFromInterface($firstCalledAt));
        }
        $pageCallMetadata->setFieldValue(
            $pageCall,
            'lastCalledAt',
            $lastCalledAt instanceof \DateTimeInterface ? \DateTime::createFromInterface($lastCalledAt) : null,
        );

        return true;
    }

    private function datedBoundaryHit(
        EntityManagerInterface $entityManager,
        string $direction,
        PageCallInterface $pageCall,
    ): ?PageCallHitInterface {
        $hit = $entityManager->createQueryBuilder()
            ->select('hit')
            ->from($this->pageCallHitClass, 'hit')
            ->where('hit.pageCall = :pageCall')
            ->andWhere('hit.calledAt IS NOT NULL')
            ->orderBy('hit.calledAt', $direction)
            ->setParameter('pageCall', $pageCall)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $hit instanceof PageCallHitInterface ? $hit : null;
    }

    /**
     * @param ClassMetadata<PageCallHitInterface> $hitMetadata
     * @param ClassMetadata<PageCallInterface>    $pageCallMetadata
     */
    private function validateMetadata(ClassMetadata $hitMetadata, ClassMetadata $pageCallMetadata): void
    {
        if (!$hitMetadata->hasField('calledAt') || !$hitMetadata->hasAssociation('pageCall')) {
            throw new \LogicException(sprintf('Configured hit %s must map "calledAt" and the "pageCall" association for retention.', $this->pageCallHitClass));
        }

        foreach (['nbCalls', 'firstCalledAt', 'lastCalledAt'] as $field) {
            if (!$pageCallMetadata->hasField($field)) {
                throw new \LogicException(sprintf('Configured page call %s must map "%s" for retention.', $this->pageCallClass, $field));
            }
        }

        if (1 !== count($hitMetadata->getIdentifierFieldNames()) || 1 !== count($pageCallMetadata->getIdentifierFieldNames())) {
            throw new \LogicException('Retention requires scalar single-field identifiers for page calls and hits.');
        }
    }
}
