<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Twig\Environment;
use Zhortein\SeoTrackingBundle\Entity\PageCall;
use Zhortein\SeoTrackingBundle\Entity\PageCallHit;
use Zhortein\SeoTrackingBundle\Tests\Fixtures\TestKernel;

final class StatisticsRenderingTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testBootstrapThemeRendersWithoutInventingUniqueVisitors(): void
    {
        $kernel = new TestKernel('test', true);
        $kernel->boot();

        try {
            $container = $kernel->getContainer()->get('test.service_container');
            self::assertInstanceOf(ContainerInterface::class, $container);
            $entityManager = $container->get(EntityManagerInterface::class);
            $twig = $container->get(Environment::class);
            self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
            self::assertInstanceOf(Environment::class, $twig);

            (new SchemaTool($entityManager))->createSchema([
                $entityManager->getClassMetadata(PageCall::class),
                $entityManager->getClassMetadata(PageCallHit::class),
            ]);

            $calledAt = new \DateTimeImmutable('2026-07-10 10:00:00 UTC');
            $pageCall = (new PageCall())
                ->setUrl('https://example.test/rendered')
                ->setGroupingKey(hash('sha256', 'rendered'))
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

            self::assertStringContainsString('class="seo-tracking-statistics"', $html);
            self::assertStringContainsString('Page calls', $html);
            self::assertStringContainsString('Human hits', $html);
            self::assertStringContainsString('https://example.test/rendered', $html);
            self::assertStringContainsString('2026-07-10', $html);
            self::assertStringContainsString('Dimension: tenant', $html);
            self::assertStringContainsString('acme', $html);
            self::assertStringNotContainsStringIgnoringCase('unique visitor', $html);
        } finally {
            $kernel->shutdown();
        }
    }
}
