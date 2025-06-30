<?php

namespace Zhortein\SeoTrackingBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Zhortein\SeoTrackingBundle\Entity\PageCall;

/**
 * @extends ServiceEntityRepository<PageCall>
 */
class PageCallRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PageCall::class);
    }
}
