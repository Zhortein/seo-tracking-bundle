<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Zhortein\SeoTrackingBundle\Journey\JourneyProviderInterface;
use Zhortein\SeoTrackingBundle\Tests\Fixtures\CustomEntityTestKernel;
use Zhortein\SeoTrackingBundle\Tests\Fixtures\Entity\CustomPageCall;
use Zhortein\SeoTrackingBundle\Tests\Fixtures\Entity\CustomPageCallHit;

final class CustomEntityJourneyProviderTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testConfiguredTraitEntitiesUseTheDefaultJourneySource(): void
    {
        $kernel = new CustomEntityTestKernel('test', true);
        $kernel->boot();

        try {
            $container = $kernel->getContainer()->get('test.service_container');
            self::assertInstanceOf(ContainerInterface::class, $container);
            $entityManager = $container->get(EntityManagerInterface::class);
            $provider = $container->get('test.journey_provider');
            self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
            self::assertInstanceOf(JourneyProviderInterface::class, $provider);

            (new SchemaTool($entityManager))->createSchema([
                $entityManager->getClassMetadata(CustomPageCall::class),
                $entityManager->getClassMetadata(CustomPageCallHit::class),
            ]);

            $calledAt = new \DateTimeImmutable('2026-07-10 10:00:00 UTC');
            $pageCall = (new CustomPageCall())
                ->setUrl('https://example.test/custom')
                ->setGroupingKey(hash('sha256', 'custom'))
                ->setRoute('custom')
                ->setNbCalls(2)
                ->setFirstCalledAt($calledAt)
                ->setLastCalledAt(\DateTime::createFromImmutable($calledAt))
                ->setBot(false);
            $first = (new CustomPageCallHit())
                ->setPageCall($pageCall)
                ->setUrl('https://example.test/custom')
                ->setCalledAt($calledAt)
                ->setBot(false);
            $second = (new CustomPageCallHit())
                ->setPageCall($pageCall)
                ->setUrl('https://example.test/custom/next')
                ->setCalledAt($calledAt->modify('+1 minute'))
                ->setParentHit($first)
                ->setBot(false);
            $entityManager->persist($pageCall);
            $entityManager->persist($first);
            $entityManager->persist($second);
            $entityManager->flush();

            $report = $provider->report();

            self::assertSame(2, $report->summary->observedHits);
            self::assertSame(1, $report->summary->linkedHits);
            self::assertSame(1, $report->summary->paths);
            self::assertCount(1, $report->topTransitions);
        } finally {
            $kernel->shutdown();
        }
    }
}
