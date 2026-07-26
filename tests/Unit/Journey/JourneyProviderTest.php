<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Unit\Journey;

use PHPUnit\Framework\TestCase;
use Zhortein\SeoTrackingBundle\Journey\DataSource\JourneyDataSourceInterface;
use Zhortein\SeoTrackingBundle\Journey\DTO\JourneyHitObservation;
use Zhortein\SeoTrackingBundle\Journey\DTO\JourneyNode;
use Zhortein\SeoTrackingBundle\Journey\Filter\JourneyFilter;
use Zhortein\SeoTrackingBundle\Journey\JourneyProvider;

final class JourneyProviderTest extends TestCase
{
    public function testItBuildsBoundedBranchingFragmentsAndTransitions(): void
    {
        $outside = new JourneyNode('https://example.test/landing', 'landing', 'landing');
        $article = new JourneyNode('https://example.test/article', 'article_show', 'article');
        $pricing = new JourneyNode('https://example.test/pricing', 'pricing', 'page');
        $contact = new JourneyNode('https://example.test/contact', 'contact', 'form');
        $demo = new JourneyNode('https://example.test/demo', 'demo', 'form');
        $observations = [
            new JourneyHitObservation(
                1,
                99,
                new \DateTimeImmutable('2026-07-10 10:00:00 UTC'),
                false,
                $article,
                new \DateTimeImmutable('2026-06-30 10:00:00 UTC'),
                $outside,
            ),
            new JourneyHitObservation(2, 1, new \DateTimeImmutable('2026-07-10 10:01:00 UTC'), false, $pricing, new \DateTimeImmutable('2026-07-10 10:00:00 UTC'), $article),
            new JourneyHitObservation(3, 2, new \DateTimeImmutable('2026-07-10 10:02:00 UTC'), false, $contact, new \DateTimeImmutable('2026-07-10 10:01:00 UTC'), $pricing),
            new JourneyHitObservation(4, 2, new \DateTimeImmutable('2026-07-10 10:03:00 UTC'), false, $demo, new \DateTimeImmutable('2026-07-10 10:01:00 UTC'), $pricing),
        ];

        $report = (new JourneyProvider(new ArrayJourneyDataSource($observations)))->report(
            transitionLimit: 10,
            pathLimit: 10,
            maxDepth: 10,
        );

        self::assertSame(4, $report->summary->observedHits);
        self::assertSame(4, $report->summary->linkedHits);
        self::assertSame(1, $report->summary->fragments);
        self::assertSame(2, $report->summary->paths);
        self::assertSame(2, $report->summary->sampledPaths);
        self::assertSame(3, $report->summary->maxDepth);
        self::assertSame(0, $report->summary->orphanedLinks);
        self::assertCount(4, $report->topTransitions);
        self::assertCount(4, $report->paths[0]->steps);
        self::assertTrue($report->paths[0]->hasKnownPredecessorOutsideFilter);
        self::assertFalse($report->paths[0]->steps[0]->withinFilter);
        self::assertSame(99, $report->paths[0]->steps[0]->hitId);
        self::assertSame('landing', $report->paths[0]->steps[0]->node->label);
    }

    public function testItBoundsCyclesAndDepthInsteadOfLooping(): void
    {
        $first = new JourneyNode('https://example.test/first', 'first', null);
        $second = new JourneyNode('https://example.test/second', 'second', null);
        $cycle = [
            new JourneyHitObservation(1, 2, new \DateTimeImmutable('2026-07-10 10:00:00 UTC'), false, $first, new \DateTimeImmutable('2026-07-10 10:01:00 UTC'), $second),
            new JourneyHitObservation(2, 1, new \DateTimeImmutable('2026-07-10 10:01:00 UTC'), false, $second, new \DateTimeImmutable('2026-07-10 10:00:00 UTC'), $first),
        ];

        $cycleReport = (new JourneyProvider(new ArrayJourneyDataSource($cycle)))->report(maxDepth: 10);

        self::assertSame(1, $cycleReport->summary->fragments);
        self::assertSame(1, $cycleReport->summary->paths);
        self::assertSame(1, $cycleReport->summary->cyclicPaths);
        self::assertTrue($cycleReport->paths[0]->cyclic);

        $chain = [
            new JourneyHitObservation(1, null, new \DateTimeImmutable('2026-07-10 10:00:00 UTC'), false, $first),
            new JourneyHitObservation(2, 1, new \DateTimeImmutable('2026-07-10 10:01:00 UTC'), false, $second, new \DateTimeImmutable('2026-07-10 10:00:00 UTC'), $first),
            new JourneyHitObservation(3, 2, new \DateTimeImmutable('2026-07-10 10:02:00 UTC'), false, $first, new \DateTimeImmutable('2026-07-10 10:01:00 UTC'), $second),
        ];

        $depthReport = (new JourneyProvider(new ArrayJourneyDataSource($chain)))->report(maxDepth: 2);

        self::assertSame(1, $depthReport->summary->truncatedPaths);
        self::assertSame(2, $depthReport->summary->maxDepth);
        self::assertTrue($depthReport->paths[0]->truncatedByDepth);
        self::assertCount(2, $depthReport->paths[0]->steps);
    }

    public function testItRejectsInvalidFiltersLimitsAndDuplicateIdentifiers(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new JourneyFilter(
            new \DateTimeImmutable('2026-07-11'),
            new \DateTimeImmutable('2026-07-10'),
        );
    }

    public function testItRejectsDuplicateIdentifiers(): void
    {
        $node = new JourneyNode('https://example.test', null, null);
        $provider = new JourneyProvider(new ArrayJourneyDataSource([
            new JourneyHitObservation(1, null, null, false, $node),
            new JourneyHitObservation(1, null, null, false, $node),
        ]));

        $this->expectException(\LogicException::class);
        $provider->report();
    }
}

final readonly class ArrayJourneyDataSource implements JourneyDataSourceInterface
{
    /**
     * @param list<JourneyHitObservation> $observations
     */
    public function __construct(private array $observations)
    {
    }

    public function observations(JourneyFilter $filter): iterable
    {
        yield from $this->observations;
    }
}
