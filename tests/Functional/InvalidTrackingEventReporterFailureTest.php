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
use Zhortein\SeoTrackingBundle\Tests\Fixtures\ThrowingInvalidEventReporterTestKernel;

final class InvalidTrackingEventReporterFailureTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testReporterFailureDoesNotChangeTheRejectionOrCreateData(): void
    {
        $kernel = new ThrowingInvalidEventReporterTestKernel('test', true);
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

            $response = $controller->track(
                new Request(content: '{"url":"/relative"}'),
                $entityManager,
                $dispatcher,
            );

            self::assertSame(400, $response->getStatusCode());
            self::assertStringNotContainsString(
                'Reporter failure',
                (string) $response->getContent(),
            );
            self::assertSame(0, $entityManager->getRepository(PageCall::class)->count([]));
            self::assertSame(0, $entityManager->getRepository(PageCallHit::class)->count([]));
        } finally {
            $kernel->shutdown();
        }
    }
}
