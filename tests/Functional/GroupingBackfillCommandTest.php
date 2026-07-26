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
use Zhortein\SeoTrackingBundle\Command\BackfillGroupingKeysCommand;
use Zhortein\SeoTrackingBundle\Entity\PageCall;
use Zhortein\SeoTrackingBundle\Entity\PageCallHit;
use Zhortein\SeoTrackingBundle\Tests\Fixtures\CustomEntityTestKernel;
use Zhortein\SeoTrackingBundle\Tests\Fixtures\TestKernel;
use Zhortein\SeoTrackingBundle\Tracking\Grouping\PageCallGroupingKeyGeneratorInterface;

#[RunTestsInSeparateProcesses]
final class GroupingBackfillCommandTest extends TestCase
{
    public function testDryRunBackfillAndExplicitConsolidationAreSafeAndResumable(): void
    {
        $kernel = new TestKernel('test', true);
        $kernel->boot();

        try {
            $container = $kernel->getContainer()->get('test.service_container');
            self::assertInstanceOf(ContainerInterface::class, $container);
            $entityManager = $container->get(EntityManagerInterface::class);
            $command = $container->get(BackfillGroupingKeysCommand::class);
            $generator = $container->get(PageCallGroupingKeyGeneratorInterface::class);
            self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
            self::assertInstanceOf(BackfillGroupingKeysCommand::class, $command);
            self::assertInstanceOf(PageCallGroupingKeyGeneratorInterface::class, $generator);

            $metadata = [
                $entityManager->getClassMetadata(PageCall::class),
                $entityManager->getClassMetadata(PageCallHit::class),
            ];
            (new SchemaTool($entityManager))->createSchema($metadata);

            $existing = $this->pageCall('https://example.test/duplicate', new \DateTimeImmutable('2026-01-03'));
            $existing->setGroupingKey($generator->generate(
                'https://example.test/duplicate',
                ['campaign' => null, 'medium' => null, 'source' => null, 'term' => null, 'content' => null],
                false,
            ));
            $firstDuplicate = $this->pageCall('https://example.test/duplicate', new \DateTimeImmutable('2026-01-01'));
            $secondDuplicate = $this->pageCall('https://example.test/duplicate', new \DateTimeImmutable('2026-01-02'));
            $unique = $this->pageCall('https://example.test/unique', new \DateTimeImmutable('2026-01-04'));

            foreach ([$existing, $firstDuplicate, $secondDuplicate, $unique] as $pageCall) {
                $entityManager->persist($pageCall);
                $entityManager->persist($this->hit($pageCall, $pageCall->getFirstCalledAt()));
            }
            $entityManager->flush();

            $dryRun = new CommandTester($command);
            self::assertSame(Command::SUCCESS, $dryRun->execute([]));
            self::assertStringContainsString('Historical rows scanned', $dryRun->getDisplay());
            self::assertStringContainsString('Duplicate conflicts', $dryRun->getDisplay());
            self::assertSame(3, $entityManager->getRepository(PageCall::class)->count(['groupingKey' => null]));

            $backfill = new CommandTester($command);
            self::assertSame(Command::SUCCESS, $backfill->execute(['--apply' => true, '--batch-size' => '1']));
            self::assertSame(2, $entityManager->getRepository(PageCall::class)->count(['groupingKey' => null]));
            self::assertSame(4, $entityManager->getRepository(PageCall::class)->count([]));

            $merge = new CommandTester($command);
            self::assertSame(Command::SUCCESS, $merge->execute([
                '--apply' => true,
                '--merge-duplicates' => true,
                '--batch-size' => '1',
            ]));

            self::assertSame(0, $entityManager->getRepository(PageCall::class)->count(['groupingKey' => null]));
            self::assertSame(2, $entityManager->getRepository(PageCall::class)->count([]));
            self::assertSame(4, $entityManager->getRepository(PageCallHit::class)->count([]));

            $merged = $entityManager->getRepository(PageCall::class)->findOneBy(['url' => 'https://example.test/duplicate']);
            self::assertInstanceOf(PageCall::class, $merged);
            self::assertSame(3, $merged->getNbCalls());
            self::assertEquals(new \DateTimeImmutable('2026-01-01'), $merged->getFirstCalledAt());
            self::assertEquals(new \DateTime('2026-01-03'), $merged->getLastCalledAt());

            $rerun = new CommandTester($command);
            self::assertSame(Command::SUCCESS, $rerun->execute(['--apply' => true, '--merge-duplicates' => true]));
            self::assertSame(2, $entityManager->getRepository(PageCall::class)->count([]));
            self::assertSame(4, $entityManager->getRepository(PageCallHit::class)->count([]));
        } finally {
            $kernel->shutdown();
        }
    }

    public function testCustomEntitiesRequireAnExplicitDuplicateMerger(): void
    {
        $kernel = new CustomEntityTestKernel('test', true);
        $kernel->boot();

        try {
            $container = $kernel->getContainer()->get('test.service_container');
            self::assertInstanceOf(ContainerInterface::class, $container);
            $command = $container->get(BackfillGroupingKeysCommand::class);
            self::assertInstanceOf(BackfillGroupingKeysCommand::class, $command);

            $tester = new CommandTester($command);
            self::assertSame(Command::FAILURE, $tester->execute([
                '--apply' => true,
                '--merge-duplicates' => true,
            ]));
            self::assertStringContainsString('requires a custom', $tester->getDisplay());
        } finally {
            $kernel->shutdown();
        }
    }

    private function pageCall(string $url, \DateTimeImmutable $calledAt): PageCall
    {
        return (new PageCall())
            ->setUrl($url)
            ->setRoute(null)
            ->setRouteArgs(null)
            ->setCampaign(null)
            ->setMedium(null)
            ->setSource(null)
            ->setTerm(null)
            ->setContent(null)
            ->setNbCalls(1)
            ->setFirstCalledAt($calledAt)
            ->setLastCalledAt(\DateTime::createFromImmutable($calledAt))
            ->setBot(false);
    }

    private function hit(PageCall $pageCall, ?\DateTimeImmutable $calledAt): PageCallHit
    {
        return (new PageCallHit())
            ->setPageCall($pageCall)
            ->setCalledAt($calledAt)
            ->setBot(false);
    }
}
