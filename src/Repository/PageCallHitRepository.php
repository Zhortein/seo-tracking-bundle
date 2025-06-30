<?php

namespace Zhortein\SeoTrackingBundle\Repository;

use Zhortein\SeoTrackingBundle\Entity\PageCallHit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PageCallHit>
 */
class PageCallHitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageCallHit::class);
    }
}
