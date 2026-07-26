<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\DataLifecycle\Grouping;

use Doctrine\ORM\EntityManagerInterface;
use Zhortein\SeoTrackingBundle\Entity\PageCallInterface;

interface DuplicatePageCallMergerInterface
{
    /**
     * @param class-string<PageCallInterface> $pageCallClass
     */
    public function supports(string $pageCallClass): bool;

    public function merge(
        EntityManagerInterface $entityManager,
        PageCallInterface $survivor,
        PageCallInterface $duplicate,
    ): void;
}
