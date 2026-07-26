<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Zhortein\SeoTrackingBundle\Controller\PageCallController;
use Zhortein\SeoTrackingBundle\Entity\PageCall;
use Zhortein\SeoTrackingBundle\Entity\PageCallHit;
use Zhortein\SeoTrackingBundle\Tests\Fixtures\CollectingInvalidTrackingEventReporter;
use Zhortein\SeoTrackingBundle\Tests\Fixtures\RateLimitedTestKernel;
use Zhortein\SeoTrackingBundle\Tracking\InvalidEvent\InvalidTrackingEventReason;
use Zhortein\SeoTrackingBundle\Tracking\RateLimit\TrackingEndpoint;

final class RateLimiterGateTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testCreationAndClosureUseIndependentSymfonyLimiters(): void
    {
        $kernel = new RateLimitedTestKernel('test', true);
        $kernel->boot();

        try {
            $container = $kernel->getContainer()->get('test.service_container');
            self::assertInstanceOf(ContainerInterface::class, $container);
            $entityManager = $container->get(EntityManagerInterface::class);
            $controller = $container->get(PageCallController::class);
            $dispatcher = $container->get(EventDispatcherInterface::class);
            self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
            self::assertInstanceOf(PageCallController::class, $controller);
            self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

            (new SchemaTool($entityManager))->createSchema([
                $entityManager->getClassMetadata(PageCall::class),
                $entityManager->getClassMetadata(PageCallHit::class),
            ]);

            $first = $controller->track(
                $this->request(['url' => 'https://example.test/first'], '198.51.100.10'),
                $entityManager,
                $dispatcher,
            );
            $rejected = $controller->track(
                $this->request(['url' => 'https://example.test/rejected'], '198.51.100.10'),
                $entityManager,
                $dispatcher,
            );
            $secondIp = $controller->track(
                $this->request(['url' => 'https://example.test/second-ip'], '198.51.100.11'),
                $entityManager,
                $dispatcher,
            );

            self::assertSame(200, $first->getStatusCode());
            self::assertSame(429, $rejected->getStatusCode());
            self::assertSame('1', $rejected->headers->get('X-RateLimit-Limit'));
            self::assertSame('0', $rejected->headers->get('X-RateLimit-Remaining'));
            self::assertNotNull($rejected->headers->get('Retry-After'));
            self::assertSame(200, $secondIp->getStatusCode());
            self::assertSame(2, $entityManager->getRepository(PageCallHit::class)->count([]));

            $trackedData = json_decode((string) $secondIp->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertIsArray($trackedData);
            $exitRequest = $this->request(['hitId' => $trackedData['hitId']], '198.51.100.11');
            $closed = $controller->exit($exitRequest, $entityManager, $dispatcher);
            $closureRejected = $controller->exit($exitRequest, $entityManager, $dispatcher);

            self::assertSame(200, $closed->getStatusCode());
            self::assertSame(429, $closureRejected->getStatusCode());
            $reporter = $container->get(CollectingInvalidTrackingEventReporter::class);
            self::assertInstanceOf(CollectingInvalidTrackingEventReporter::class, $reporter);
            self::assertCount(2, $reporter->events);
            self::assertSame(InvalidTrackingEventReason::RATE_LIMITED, $reporter->events[0]->reason);
            self::assertSame(TrackingEndpoint::CREATION, $reporter->events[0]->endpoint);
            self::assertSame(InvalidTrackingEventReason::RATE_LIMITED, $reporter->events[1]->reason);
            self::assertSame(TrackingEndpoint::CLOSURE, $reporter->events[1]->endpoint);
        } finally {
            $kernel->shutdown();
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function request(array $payload, string $remoteAddress): Request
    {
        return new Request(
            server: ['REMOTE_ADDR' => $remoteAddress],
            content: json_encode($payload, JSON_THROW_ON_ERROR),
        );
    }
}
