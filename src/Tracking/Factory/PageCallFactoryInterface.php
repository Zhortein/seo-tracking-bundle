<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tracking\Factory;

use Zhortein\SeoTrackingBundle\Entity\PageCallInterface;

interface PageCallFactoryInterface
{
    public function create(): PageCallInterface;
}
