<?php

namespace Zhortein\SeoTrackingBundle\Repository;

use Zhortein\SeoTrackingBundle\Entity\PageCall;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

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
