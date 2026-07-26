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
use Zhortein\SeoTrackingBundle\Tests\Fixtures\BotClassifierTestKernel;

final class BotClassifierReplacementTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testApplicationClassifierControlsThePersistedBotFlag(): void
    {
        $kernel = new BotClassifierTestKernel('test', true);
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
                new Request(
                    server: ['HTTP_USER_AGENT' => 'Mozilla/5.0'],
                    content: '{"url":"https://example.test/classified"}',
                ),
                $entityManager,
                $dispatcher,
            );

            self::assertSame(200, $response->getStatusCode());
            $pageCall = $entityManager->getRepository(PageCall::class)->findOneBy([]);
            $hit = $entityManager->getRepository(PageCallHit::class)->findOneBy([]);
            self::assertInstanceOf(PageCall::class, $pageCall);
            self::assertInstanceOf(PageCallHit::class, $hit);
            self::assertTrue($pageCall->isBot());
            self::assertTrue($hit->isBot());
        } finally {
            $kernel->shutdown();
        }
    }
}
