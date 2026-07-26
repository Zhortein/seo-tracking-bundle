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
use Zhortein\SeoTrackingBundle\Tests\Fixtures\InvalidEventReportingTestKernel;
use Zhortein\SeoTrackingBundle\Tracking\InvalidEvent\InvalidTrackingEventReason;
use Zhortein\SeoTrackingBundle\Tracking\RateLimit\TrackingEndpoint;

final class InvalidTrackingEventReportingTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testOnlyRejectedRequestsProduceSafeTypedEvents(): void
    {
        $kernel = new InvalidEventReportingTestKernel('test', true);
        $kernel->boot();

        try {
            $container = $kernel->getContainer()->get('test.service_container');
            self::assertInstanceOf(ContainerInterface::class, $container);
            $entityManager = $container->get(EntityManagerInterface::class);
            $controller = $container->get(PageCallController::class);
            $dispatcher = $container->get(EventDispatcherInterface::class);
            $reporter = $container->get(CollectingInvalidTrackingEventReporter::class);
            self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
            self::assertInstanceOf(PageCallController::class, $controller);
            self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);
            self::assertInstanceOf(CollectingInvalidTrackingEventReporter::class, $reporter);

            (new SchemaTool($entityManager))->createSchema([
                $entityManager->getClassMetadata(PageCall::class),
                $entityManager->getClassMetadata(PageCallHit::class),
            ]);

            $accepted = $controller->track(
                $this->request('{"url":"https://example.test/accepted"}'),
                $entityManager,
                $dispatcher,
            );
            self::assertSame(200, $accepted->getStatusCode());
            self::assertSame(0, count($reporter->events));

            self::assertSame(400, $controller->track(
                $this->request('{'),
                $entityManager,
                $dispatcher,
            )->getStatusCode());
            self::assertSame(400, $controller->track(
                $this->request('["not-an-object"]'),
                $entityManager,
                $dispatcher,
            )->getStatusCode());
            self::assertSame(400, $controller->track(
                $this->request('{"url":"/relative","secret":"must-not-be-reported"}'),
                $entityManager,
                $dispatcher,
            )->getStatusCode());
            self::assertSame(400, $controller->exit(
                $this->request('{'),
                $entityManager,
                $dispatcher,
            )->getStatusCode());
            self::assertSame(400, $controller->exit(
                $this->request('[]'),
                $entityManager,
                $dispatcher,
            )->getStatusCode());
            self::assertSame(400, $controller->exit(
                $this->request('{}'),
                $entityManager,
                $dispatcher,
            )->getStatusCode());
            self::assertSame(404, $controller->exit(
                $this->request('{"hitId":999999}'),
                $entityManager,
                $dispatcher,
            )->getStatusCode());

            self::assertSame([
                InvalidTrackingEventReason::MALFORMED_JSON,
                InvalidTrackingEventReason::UNSUPPORTED_PAYLOAD,
                InvalidTrackingEventReason::INVALID_PAYLOAD,
                InvalidTrackingEventReason::MALFORMED_JSON,
                InvalidTrackingEventReason::UNSUPPORTED_PAYLOAD,
                InvalidTrackingEventReason::INVALID_PAYLOAD,
                InvalidTrackingEventReason::UNKNOWN_HIT,
            ], array_map(static fn ($event) => $event->reason, $reporter->events));
            self::assertSame([
                TrackingEndpoint::CREATION,
                TrackingEndpoint::CREATION,
                TrackingEndpoint::CREATION,
                TrackingEndpoint::CLOSURE,
                TrackingEndpoint::CLOSURE,
                TrackingEndpoint::CLOSURE,
                TrackingEndpoint::CLOSURE,
            ], array_map(static fn ($event) => $event->endpoint, $reporter->events));
            self::assertSame(1, $entityManager->getRepository(PageCallHit::class)->count([]));

            foreach ($reporter->events as $event) {
                self::assertSame([
                    'reason',
                    'endpoint',
                    'occurredAt',
                    'method',
                    'route',
                    'contentType',
                    'contentLength',
                ], array_keys(get_object_vars($event)));
            }
        } finally {
            $kernel->shutdown();
        }
    }

    private function request(string $content): Request
    {
        return new Request(
            server: [
                'REMOTE_ADDR' => '203.0.113.42',
                'HTTP_USER_AGENT' => 'SecretAgent/1.0',
                'CONTENT_TYPE' => 'application/json',
            ],
            content: $content,
        );
    }
}
