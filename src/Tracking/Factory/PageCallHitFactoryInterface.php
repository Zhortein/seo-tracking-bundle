<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tracking\Factory;

use Zhortein\SeoTrackingBundle\Entity\PageCallHitInterface;

interface PageCallHitFactoryInterface
{
    public function create(): PageCallHitInterface;
}
