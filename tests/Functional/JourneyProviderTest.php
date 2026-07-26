<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Zhortein\SeoTrackingBundle\Entity\PageCall;
use Zhortein\SeoTrackingBundle\Entity\PageCallHit;
use Zhortein\SeoTrackingBundle\Journey\Filter\JourneyFilter;
use Zhortein\SeoTrackingBundle\Journey\JourneyProviderInterface;
use Zhortein\SeoTrackingBundle\Tests\Fixtures\TestKernel;

final class JourneyProviderTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testDoctrineSourceIncludesImmediateParentOutsideThePeriod(): void
    {
        $kernel = new TestKernel('test', true);
        $kernel->boot();

        try {
            $container = $kernel->getContainer()->get('test.service_container');
            self::assertInstanceOf(ContainerInterface::class, $container);
            $entityManager = $container->get(EntityManagerInterface::class);
            $provider = $container->get(JourneyProviderInterface::class);
            self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
            self::assertInstanceOf(JourneyProviderInterface::class, $provider);

            (new SchemaTool($entityManager))->createSchema([
                $entityManager->getClassMetadata(PageCall::class),
                $entityManager->getClassMetadata(PageCallHit::class),
            ]);

            $landing = $this->hit($entityManager, 'landing', new \DateTimeImmutable('2026-06-30 10:00:00 UTC'));
            $article = $this->hit($entityManager, 'article', new \DateTimeImmutable('2026-07-10 10:00:00 UTC'), $landing);
            $this->hit($entityManager, 'contact', new \DateTimeImmutable('2026-07-10 10:01:00 UTC'), $article);
            $this->hit($entityManager, 'robot', new \DateTimeImmutable('2026-07-10 10:02:00 UTC'), null, true);
            $entityManager->flush();

            $report = $provider->report(new JourneyFilter(
                from: new \DateTimeImmutable('2026-07-01 00:00:00 UTC'),
                to: new \DateTimeImmutable('2026-07-31 23:59:59 UTC'),
                bot: false,
            ));

            self::assertSame(2, $report->summary->observedHits);
            self::assertSame(2, $report->summary->linkedHits);
            self::assertSame(1, $report->summary->fragments);
            self::assertSame(1, $report->summary->paths);
            self::assertCount(2, $report->topTransitions);
            self::assertCount(3, $report->paths[0]->steps);
            self::assertFalse($report->paths[0]->steps[0]->withinFilter);
            self::assertSame('landing', $report->paths[0]->steps[0]->node->route);
            self::assertSame('contact', $report->paths[0]->steps[2]->node->route);
        } finally {
            $kernel->shutdown();
        }
    }

    private function hit(
        EntityManagerInterface $entityManager,
        string $slug,
        \DateTimeImmutable $calledAt,
        ?PageCallHit $parent = null,
        bool $bot = false,
    ): PageCallHit {
        $pageCall = (new PageCall())
            ->setUrl('https://example.test/'.$slug)
            ->setGroupingKey(hash('sha256', $slug))
            ->setRoute($slug)
            ->setNbCalls(1)
            ->setFirstCalledAt($calledAt)
            ->setLastCalledAt(\DateTime::createFromImmutable($calledAt))
            ->setBot($bot);
        $hit = (new PageCallHit())
            ->setPageCall($pageCall)
            ->setUrl('https://example.test/'.$slug)
            ->setCalledAt($calledAt)
            ->setPageType('page')
            ->setParentHit($parent)
            ->setBot($bot);

        $entityManager->persist($pageCall);
        $entityManager->persist($hit);

        return $hit;
    }
}
