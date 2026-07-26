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
use Zhortein\SeoTrackingBundle\Tests\Fixtures\CustomEntityTestKernel;
use Zhortein\SeoTrackingBundle\Tests\Fixtures\Entity\CustomPageCall;
use Zhortein\SeoTrackingBundle\Tests\Fixtures\Entity\CustomPageCallHit;

final class CustomEntityControllerTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testConfiguredEntitiesAreUsedEndToEnd(): void
    {
        $kernel = new CustomEntityTestKernel('test', true);
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
                $entityManager->getClassMetadata(CustomPageCall::class),
                $entityManager->getClassMetadata(CustomPageCallHit::class),
            ]);

            $response = $controller->track(
                new Request(content: '{"url":"https://example.test/custom"}'),
                $entityManager,
                $dispatcher,
            );

            self::assertSame(200, $response->getStatusCode(), (string) $response->getContent());
            self::assertSame(1, $entityManager->getRepository(CustomPageCall::class)->count([]));
            self::assertSame(1, $entityManager->getRepository(CustomPageCallHit::class)->count([]));
        } finally {
            $kernel->shutdown();
        }
    }
}
