<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Statistics\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class StatisticsExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'seo_tracking_statistics_report',
                [StatisticsRuntime::class, 'report'],
            ),
            new TwigFunction(
                'seo_tracking_statistics',
                [StatisticsRuntime::class, 'render'],
                ['is_safe' => ['html']],
            ),
        ];
    }
}
