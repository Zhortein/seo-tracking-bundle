<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Statistics\Twig;

use Twig\Environment;
use Twig\Extension\RuntimeExtensionInterface;
use Zhortein\SeoTrackingBundle\Statistics\DTO\StatisticsReport;
use Zhortein\SeoTrackingBundle\Statistics\Filter\StatisticsFilter;
use Zhortein\SeoTrackingBundle\Statistics\StatisticsProviderInterface;

final readonly class StatisticsRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private StatisticsProviderInterface $provider,
        private Environment $twig,
        private ?string $defaultTemplate,
    ) {
    }

    public function report(?StatisticsFilter $filter = null, int $limit = 10): StatisticsReport
    {
        return $this->provider->report($filter, $limit);
    }

    public function render(
        StatisticsReport|StatisticsFilter|null $statistics = null,
        ?string $template = null,
        int $limit = 10,
    ): string {
        $report = $statistics instanceof StatisticsReport
            ? $statistics
            : $this->provider->report($statistics, $limit);
        $template ??= $this->defaultTemplate;

        if (null === $template || '' === trim($template)) {
            throw new \LogicException('No statistics template is enabled. Pass a template or configure "statistics.template".');
        }

        return $this->twig->render($template, ['report' => $report]);
    }
}
