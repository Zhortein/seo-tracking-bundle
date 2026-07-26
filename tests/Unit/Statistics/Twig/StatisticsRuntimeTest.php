<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Unit\Statistics\Twig;

use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Zhortein\SeoTrackingBundle\Statistics\DTO\StatisticsReport;
use Zhortein\SeoTrackingBundle\Statistics\DTO\StatisticsSummary;
use Zhortein\SeoTrackingBundle\Statistics\Filter\StatisticsFilter;
use Zhortein\SeoTrackingBundle\Statistics\StatisticsProviderInterface;
use Zhortein\SeoTrackingBundle\Statistics\Twig\StatisticsRuntime;

final class StatisticsRuntimeTest extends TestCase
{
    public function testDisabledThemeRequiresAnExplicitTemplateButKeepsTheBusinessApiAvailable(): void
    {
        $report = new StatisticsReport(
            new StatisticsFilter(),
            new StatisticsSummary(0, 0, 0, 0, 0, null, null),
            [],
            [],
            [],
            [],
            [],
            [],
            [],
            [],
        );
        $provider = $this->createStub(StatisticsProviderInterface::class);
        $provider->method('report')->willReturn($report);
        $runtime = new StatisticsRuntime(
            $provider,
            new Environment(new ArrayLoader(['custom.html.twig' => '{{ report.summary.pageCalls }}'])),
            null,
        );

        self::assertSame($report, $runtime->report());
        self::assertSame('0', $runtime->render($report, 'custom.html.twig'));

        $this->expectException(\LogicException::class);
        $runtime->render($report);
    }
}
