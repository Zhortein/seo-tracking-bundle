<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tracking\Ip;

interface IpAnonymizerInterface
{
    public function anonymize(?string $address): ?string;
}
