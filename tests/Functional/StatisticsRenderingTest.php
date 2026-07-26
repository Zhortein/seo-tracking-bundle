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
            $translator = $container->get(TranslatorInterface::class);
            self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
            self::assertInstanceOf(Environment::class, $twig);
            self::assertInstanceOf(TranslatorInterface::class, $translator);
            self::assertInstanceOf(LocaleAwareInterface::class, $translator);

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
            self::assertStringContainsString('<figure', $html);
            self::assertStringContainsString('role="progressbar"', $html);
            self::assertStringContainsString('Daily page-call evolution data', $html);
            self::assertStringContainsString('Dimension: tenant', $html);
            self::assertStringContainsString('acme', $html);
            self::assertStringNotContainsStringIgnoringCase('unique visitor', $html);

            $translator->setLocale('fr');
            $french = $twig->createTemplate('{{ seo_tracking_statistics() }}')->render();
            self::assertStringContainsString('Appels de pages', $french);
            self::assertStringContainsString('Consultations humaines', $french);
            self::assertStringContainsString('Évolution', $french);
            self::assertStringContainsString('Dimension : tenant', $french);
        } finally {
            $kernel->shutdown();
        }
    }
}
