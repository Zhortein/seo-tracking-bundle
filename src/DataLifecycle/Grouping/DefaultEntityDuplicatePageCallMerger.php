<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\DataLifecycle\Grouping;

use Doctrine\ORM\EntityManagerInterface;
use Zhortein\SeoTrackingBundle\Entity\PageCall;
use Zhortein\SeoTrackingBundle\Entity\PageCallHitInterface;
use Zhortein\SeoTrackingBundle\Entity\PageCallInterface;

final readonly class DefaultEntityDuplicatePageCallMerger implements DuplicatePageCallMergerInterface
{
    /**
     * @param class-string<PageCallHitInterface> $pageCallHitClass
     */
    public function __construct(private string $pageCallHitClass)
    {
    }

    public function supports(string $pageCallClass): bool
    {
        return PageCall::class === $pageCallClass;
    }

    public function merge(
        EntityManagerInterface $entityManager,
        PageCallInterface $survivor,
        PageCallInterface $duplicate,
    ): void {
        if (!$survivor instanceof PageCall || !$duplicate instanceof PageCall) {
            throw new \LogicException(sprintf('%s only supports the bundle default %s entity.', self::class, PageCall::class));
        }

        if ($survivor === $duplicate) {
            throw new \LogicException('A page call cannot be merged into itself.');
        }

        $entityManager->createQueryBuilder()
            ->update($this->pageCallHitClass, 'hit')
            ->set('hit.pageCall', ':survivor')
            ->where('hit.pageCall = :duplicate')
            ->setParameter('survivor', $survivor)
            ->setParameter('duplicate', $duplicate)
            ->getQuery()
            ->execute();

        $survivor->setNbCalls(($survivor->getNbCalls() ?? 0) + ($duplicate->getNbCalls() ?? 0));

        $survivorFirstCall = $survivor->getFirstCalledAt();
        $duplicateFirstCall = $duplicate->getFirstCalledAt();
        if (null !== $duplicateFirstCall && (null === $survivorFirstCall || $duplicateFirstCall < $survivorFirstCall)) {
            $survivor->setFirstCalledAt($duplicateFirstCall);
        }

        $survivorLastCall = $survivor->getLastCalledAt();
        $duplicateLastCall = $duplicate->getLastCalledAt();
        if (null !== $duplicateLastCall && (null === $survivorLastCall || $duplicateLastCall > $survivorLastCall)) {
            $survivor->setLastCalledAt($duplicateLastCall);
        }

        $entityManager->remove($duplicate);
    }
}
