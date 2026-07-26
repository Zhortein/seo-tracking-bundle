<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Zhortein\SeoTrackingBundle\Command\PurgeTrackingHitsCommand;
use Zhortein\SeoTrackingBundle\Entity\PageCall;
use Zhortein\SeoTrackingBundle\Entity\PageCallHit;
use Zhortein\SeoTrackingBundle\Tests\Fixtures\CustomEntityTestKernel;
use Zhortein\SeoTrackingBundle\Tests\Fixtures\Entity\CustomPageCall;
use Zhortein\SeoTrackingBundle\Tests\Fixtures\Entity\CustomPageCallHit;
use Zhortein\SeoTrackingBundle\Tests\Fixtures\TestKernel;

#[RunTestsInSeparateProcesses]
final class RetentionCommandTest extends TestCase
{
    public function testDryRunAndApplyKeepAggregatesCoherentAndLeaveUndatedHitsUntouched(): void
    {
        $kernel = new TestKernel('test', true);
        $kernel->boot();

        try {
            [$entityManager, $command] = $this->services($kernel);
            $this->createDefaultSchema($entityManager);

            $mixed = $this->pageCall('https://example.test/mixed', 2, '2026-01-01', '2026-07-01');
            $old = $this->hit($mixed, '2026-01-01');
            $recent = $this->hit($mixed, '2026-07-01');
            $recent->setParentHit($old);

            $emptied = $this->pageCall('https://example.test/emptied', 1, '2026-02-01', '2026-02-01');
            $oldOnly = $this->hit($emptied, '2026-02-01');

            $undated = $this->pageCall('https://example.test/undated', 1, '2026-03-01', '2026-03-01');
            $undatedHit = $this->hit($undated, null);

            foreach ([$mixed, $emptied, $undated, $old, $recent, $oldOnly, $undatedHit] as $entity) {
                $entityManager->persist($entity);
            }
            $entityManager->flush();

            $disabled = new CommandTester($command);
            self::assertSame(Command::FAILURE, $disabled->execute([]));
            self::assertStringContainsString('Retention is disabled', $disabled->getDisplay());

            $dryRun = new CommandTester($command);
            self::assertSame(Command::SUCCESS, $dryRun->execute([
                '--before' => '2026-06-01T00:00:00+00:00',
            ]));
            self::assertStringContainsString('Candidate hits', $dryRun->getDisplay());
            self::assertStringContainsString('Undated hits left unchanged', $dryRun->getDisplay());
            self::assertSame(4, $entityManager->getRepository(PageCallHit::class)->count([]));

            $apply = new CommandTester($command);
            self::assertSame(Command::SUCCESS, $apply->execute([
                '--apply' => true,
                '--before' => '2026-06-01T00:00:00+00:00',
                '--batch-size' => '1',
            ]));

            self::assertSame(2, $entityManager->getRepository(PageCallHit::class)->count([]));
            self::assertSame(3, $entityManager->getRepository(PageCall::class)->count([]));

            $reloadedMixed = $entityManager->getRepository(PageCall::class)->findOneBy(['url' => 'https://example.test/mixed']);
            $reloadedEmpty = $entityManager->getRepository(PageCall::class)->findOneBy(['url' => 'https://example.test/emptied']);
            $reloadedRecent = $entityManager->getRepository(PageCallHit::class)->findOneBy(['pageCall' => $reloadedMixed]);
            self::assertInstanceOf(PageCall::class, $reloadedMixed);
            self::assertInstanceOf(PageCall::class, $reloadedEmpty);
            self::assertInstanceOf(PageCallHit::class, $reloadedRecent);
            self::assertSame(1, $reloadedMixed->getNbCalls());
            self::assertEquals(new \DateTimeImmutable('2026-07-01'), $reloadedMixed->getFirstCalledAt());
            self::assertEquals(new \DateTime('2026-07-01'), $reloadedMixed->getLastCalledAt());
            self::assertNull($reloadedRecent->getParentHit());
            self::assertSame(0, $reloadedEmpty->getNbCalls());
            self::assertNull($reloadedEmpty->getLastCalledAt());

            $rerun = new CommandTester($command);
            self::assertSame(Command::SUCCESS, $rerun->execute([
                '--apply' => true,
                '--before' => '2026-06-01T00:00:00+00:00',
            ]));
            self::assertSame(2, $entityManager->getRepository(PageCallHit::class)->count([]));
        } finally {
            $kernel->shutdown();
        }
    }

    public function testEmptyPageCallRemovalIsExplicit(): void
    {
        $kernel = new TestKernel('test', true);
        $kernel->boot();

        try {
            [$entityManager, $command] = $this->services($kernel);
            $this->createDefaultSchema($entityManager);

            $pageCall = $this->pageCall('https://example.test/remove', 1, '2025-01-01', '2025-01-01');
            $entityManager->persist($pageCall);
            $entityManager->persist($this->hit($pageCall, '2025-01-01'));
            $entityManager->flush();

            $tester = new CommandTester($command);
            self::assertSame(Command::SUCCESS, $tester->execute([
                '--apply' => true,
                '--before' => '2026-01-01T00:00:00+00:00',
                '--remove-empty-page-calls' => true,
            ]));
            self::assertSame(0, $entityManager->getRepository(PageCallHit::class)->count([]));
            self::assertSame(0, $entityManager->getRepository(PageCall::class)->count([]));
        } finally {
            $kernel->shutdown();
        }
    }

    public function testConfiguredEntitiesArePurgedWithoutUsingDefaultRepositories(): void
    {
        $kernel = new CustomEntityTestKernel('test', true);
        $kernel->boot();

        try {
            [$entityManager, $command] = $this->services($kernel);
            $metadata = [
                $entityManager->getClassMetadata(CustomPageCall::class),
                $entityManager->getClassMetadata(CustomPageCallHit::class),
            ];
            (new SchemaTool($entityManager))->createSchema($metadata);

            $pageCall = (new CustomPageCall())
                ->setUrl('https://example.test/custom')
                ->setRoute(null)
                ->setRouteArgs(null)
                ->setCampaign(null)
                ->setMedium(null)
                ->setSource(null)
                ->setTerm(null)
                ->setContent(null)
                ->setNbCalls(1)
                ->setFirstCalledAt(new \DateTimeImmutable('2025-01-01'))
                ->setLastCalledAt(new \DateTime('2025-01-01'))
                ->setBot(false);
            $hit = (new CustomPageCallHit())
                ->setPageCall($pageCall)
                ->setCalledAt(new \DateTimeImmutable('2025-01-01'))
                ->setBot(false);
            $entityManager->persist($pageCall);
            $entityManager->persist($hit);
            $entityManager->flush();

            $tester = new CommandTester($command);
            self::assertSame(Command::SUCCESS, $tester->execute([
                '--apply' => true,
                '--before' => '2026-01-01T00:00:00+00:00',
            ]));
            self::assertSame(0, $entityManager->getRepository(CustomPageCallHit::class)->count([]));
            self::assertSame(1, $entityManager->getRepository(CustomPageCall::class)->count([]));

            $remaining = $entityManager->getRepository(CustomPageCall::class)->findOneBy([]);
            self::assertInstanceOf(CustomPageCall::class, $remaining);
            self::assertSame(0, $remaining->getNbCalls());
        } finally {
            $kernel->shutdown();
        }
    }

    /**
     * @return array{EntityManagerInterface, PurgeTrackingHitsCommand}
     */
    private function services(TestKernel $kernel): array
    {
        $container = $kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $entityManager = $container->get(EntityManagerInterface::class);
        $command = $container->get(PurgeTrackingHitsCommand::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        self::assertInstanceOf(PurgeTrackingHitsCommand::class, $command);

        return [$entityManager, $command];
    }

    private function createDefaultSchema(EntityManagerInterface $entityManager): void
    {
        $metadata = [
            $entityManager->getClassMetadata(PageCall::class),
            $entityManager->getClassMetadata(PageCallHit::class),
        ];
        (new SchemaTool($entityManager))->createSchema($metadata);
    }

    private function pageCall(
        string $url,
        int $calls,
        string $firstCalledAt,
        string $lastCalledAt,
    ): PageCall {
        return (new PageCall())
            ->setUrl($url)
            ->setRoute(null)
            ->setRouteArgs(null)
            ->setCampaign(null)
            ->setMedium(null)
            ->setSource(null)
            ->setTerm(null)
            ->setContent(null)
            ->setNbCalls($calls)
            ->setFirstCalledAt(new \DateTimeImmutable($firstCalledAt))
            ->setLastCalledAt(new \DateTime($lastCalledAt))
            ->setBot(false);
    }

    private function hit(PageCall $pageCall, ?string $calledAt): PageCallHit
    {
        return (new PageCallHit())
            ->setPageCall($pageCall)
            ->setCalledAt(null === $calledAt ? null : new \DateTimeImmutable($calledAt))
            ->setBot(false);
    }
}
