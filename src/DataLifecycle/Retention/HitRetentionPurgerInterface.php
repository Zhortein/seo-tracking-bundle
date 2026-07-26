<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\DataLifecycle\Retention;

interface HitRetentionPurgerInterface
{
    public function purge(RetentionPurgeOptions $options): RetentionPurgeResult;
}
