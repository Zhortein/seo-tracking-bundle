<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Contracts\Translation\LocaleAwareInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;
use Zhortein\SeoTrackingBundle\Entity\PageCall;
use Zhortein\SeoTrackingBundle\Entity\PageCallHit;
use Zhortein\SeoTrackingBundle\Statistics\DTO\RankedValue;
use Zhortein\SeoTrackingBundle\Statistics\DTO\StatisticsReport;
use Zhortein\SeoTrackingBundle\Statistics\DTO\StatisticsSummary;
use Zhortein\SeoTrackingBundle\Statistics\DTO\TrendPoint;
use Zhortein\SeoTrackingBundle\Statistics\Filter\StatisticsFilter;
use Zhortein\SeoTrackingBundle\Tests\Fixtures\Html5StatisticsTestKernel;

final class Html5StatisticsRenderingTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testFrameworkNeutralThemeIsTranslatedEscapedAndAccessible(): void
    {
        $kernel = new Html5StatisticsTestKernel('test', true);
        $kernel->boot();

        try {
            $container = $kernel->getContainer()->get('test.service_container');
            self::assertInstanceOf(ContainerInterface::class, $container);
            $entityManager = $container->get(EntityManagerInterface::class);
            $twig = $container->get(Environment::class);
            $translator = $container->get(TranslatorInterface::class);
            self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
            self::assertInstanceOf(Environment::class, $twig);
            self::assertInstanceOf(TranslatorInterface::class, $translator);
            self::assertInstanceOf(LocaleAwareInterface::class, $translator);

            (new SchemaTool($entityManager))->createSchema([
                $entityManager->getClassMetadata(PageCall::class),
                $entityManager->getClassMetadata(PageCallHit::class),
            ]);

            $empty = $twig->createTemplate('{{ seo_tracking_statistics() }}')->render();
            self::assertStringContainsString('<h2>SEO statistics</h2>', $empty);
            self::assertStringContainsString('<figure aria-label="Daily page-call evolution chart">', $empty);
            self::assertStringContainsString('<table>', $empty);
            self::assertStringContainsString('<caption>Daily page-call evolution data</caption>', $empty);
            self::assertStringContainsString('No data', $empty);

            $calledAt = new \DateTimeImmutable('2026-07-10 10:00:00 UTC');
            $pageCall = (new PageCall())
                ->setUrl('https://example.test/rendered?<script>alert(1)</script>')
                ->setGroupingKey(hash('sha256', 'html5-rendered'))
                ->setNbCalls(1)
                ->setFirstCalledAt($calledAt)
                ->setLastCalledAt(\DateTime::createFromImmutable($calledAt))
                ->setBot(false);
            $hit = (new PageCallHit())
                ->setPageCall($pageCall)
                ->setCalledAt($calledAt)
                ->setExitedAt($calledAt->modify('+8 seconds'))
                ->setPageType('article')
                ->setDimensions(['tenant' => 'acme'])
                ->setBot(false);
            $entityManager->persist($pageCall);
            $entityManager->persist($hit);
            $entityManager->flush();

            $html = $twig->createTemplate('{{ seo_tracking_statistics() }}')->render();
            self::assertStringContainsString('<progress max="1" value="1">', $html);
            self::assertStringContainsString('<time datetime="2026-07-10">', $html);
            self::assertStringContainsString(
                'https://example.test/rendered?&lt;script&gt;alert(1)&lt;/script&gt;',
                $html,
            );
            self::assertStringNotContainsString('<script>alert(1)</script>', $html);

            $translator->setLocale('fr');
            $french = $twig->createTemplate('{{ seo_tracking_statistics() }}')->render();
            self::assertStringContainsString('<h2>Statistiques SEO</h2>', $french);
            self::assertStringContainsString('Classements', $french);
            self::assertStringContainsString('Dimension : tenant', $french);

            $zeroReport = new StatisticsReport(
                new StatisticsFilter(),
                new StatisticsSummary(0, 0, 0, 0, 0, null, null),
                [new RankedValue('zero', 0)],
                [],
                [],
                [],
                [],
                [],
                [],
                [new TrendPoint(new \DateTimeImmutable('2026-07-01 UTC'), 0, 0, 0)],
            );
            $zero = $twig->createTemplate('{{ seo_tracking_statistics(report) }}')->render(['report' => $zeroReport]);
            self::assertStringContainsString('<progress max="1" value="0">', $zero);
            self::assertStringNotContainsStringIgnoringCase('nan', $zero);
            self::assertStringNotContainsStringIgnoringCase('infinity', $zero);
        } finally {
            $kernel->shutdown();
        }
    }
}
