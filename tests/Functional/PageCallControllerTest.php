<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Functional;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Zhortein\SeoTrackingBundle\Controller\PageCallController;
use Zhortein\SeoTrackingBundle\Entity\PageCall;
use Zhortein\SeoTrackingBundle\Entity\PageCallHit;
use Zhortein\SeoTrackingBundle\Tests\Fixtures\TestKernel;

#[RunTestsInSeparateProcesses]
final class PageCallControllerTest extends TestCase
{
    private TestKernel $kernel;
    private EntityManagerInterface $entityManager;
    private PageCallController $controller;
    private EventDispatcherInterface $dispatcher;

    protected function setUp(): void
    {
        $this->kernel = new TestKernel('test', true);
        $this->kernel->boot();

        $container = $this->kernel->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $container);
        $entityManager = $container->get(EntityManagerInterface::class);
        $controller = $container->get(PageCallController::class);
        $dispatcher = $container->get(EventDispatcherInterface::class);

        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        self::assertInstanceOf(PageCallController::class, $controller);
        self::assertInstanceOf(EventDispatcherInterface::class, $dispatcher);

        $this->entityManager = $entityManager;
        $this->controller = $controller;
        $this->dispatcher = $dispatcher;

        $metadata = [
            $this->entityManager->getClassMetadata(PageCall::class),
            $this->entityManager->getClassMetadata(PageCallHit::class),
        ];
        (new SchemaTool($this->entityManager))->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        $this->kernel->shutdown();
    }

    public function testMinimalPayloadIsTrackedAndNullableUtmValuesAreGrouped(): void
    {
        $first = $this->track([
            'url' => 'https://example.test/article',
        ], '2001:db8:1234:5678:90ab:cdef:1234:5678');
        $second = $this->track([
            'url' => 'https://example.test/article',
        ], '2001:db8:1234:5678:ffff::1');

        self::assertSame(200, $first->getStatusCode());
        self::assertSame(200, $second->getStatusCode());
        self::assertSame(1, $this->entityManager->getRepository(PageCall::class)->count([]));
        self::assertSame(2, $this->entityManager->getRepository(PageCallHit::class)->count([]));

        $pageCall = $this->entityManager->getRepository(PageCall::class)->findOneBy([]);
        self::assertInstanceOf(PageCall::class, $pageCall);
        self::assertSame(2, $pageCall->getNbCalls());
        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $pageCall->getGroupingKey());

        $hits = $this->entityManager->getRepository(PageCallHit::class)->findBy([], ['id' => 'ASC']);
        self::assertSame('2001:db8:1234:5678::', $hits[0]->getAnonymizedIp());
        self::assertSame('2001:db8:1234:5678::', $hits[1]->getAnonymizedIp());
    }

    public function testCanonicalAndObservedUrlsAreBothPreserved(): void
    {
        $this->track([
            'url' => 'https://example.test/article?variant=one',
            'canonicalUrl' => 'https://example.test/article',
        ]);

        $pageCall = $this->entityManager->getRepository(PageCall::class)->findOneBy([]);
        $hit = $this->entityManager->getRepository(PageCallHit::class)->findOneBy([]);

        self::assertInstanceOf(PageCall::class, $pageCall);
        self::assertInstanceOf(PageCallHit::class, $hit);
        self::assertSame('https://example.test/article', $pageCall->getUrl());
        self::assertSame('https://example.test/article', $pageCall->getCanonicalUrl());
        self::assertSame('https://example.test/article?variant=one', $hit->getUrl());
    }

    public function testExplicitDimensionsAreNormalizedAndStoredOnTheHit(): void
    {
        $response = $this->track([
            'url' => 'https://example.test/pricing',
            'dimensions' => [
                'plan' => 'professional',
                'authenticated' => true,
                'category_id' => 12,
            ],
        ]);

        self::assertSame(200, $response->getStatusCode());
        $hit = $this->entityManager->getRepository(PageCallHit::class)->findOneBy([]);
        self::assertInstanceOf(PageCallHit::class, $hit);
        self::assertSame([
            'authenticated' => true,
            'category_id' => 12,
            'plan' => 'professional',
        ], $hit->getDimensions());
    }

    public function testInvalidDimensionsRejectTheWholeHit(): void
    {
        $response = $this->track([
            'url' => 'https://example.test/pricing',
            'dimensions' => [
                'customer' => ['email' => 'not-collected@example.test'],
            ],
        ]);

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(0, $this->entityManager->getRepository(PageCallHit::class)->count([]));
    }

    public function testMalformedJsonAndInvalidFieldsReturnBadRequest(): void
    {
        $malformed = $this->controller->track(
            new Request(content: '{'),
            $this->entityManager,
            $this->dispatcher,
        );
        $invalid = $this->track(['url' => '/relative']);

        self::assertSame(400, $malformed->getStatusCode());
        self::assertSame(400, $invalid->getStatusCode());
        self::assertSame(0, $this->entityManager->getRepository(PageCallHit::class)->count([]));
    }

    public function testHitClosureIsIdempotent(): void
    {
        $tracked = $this->track(['url' => 'https://example.test/article']);
        $trackedData = json_decode((string) $tracked->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($trackedData);

        $request = new Request(content: json_encode(['hitId' => $trackedData['hitId']], JSON_THROW_ON_ERROR));
        $first = $this->controller->exit($request, $this->entityManager, $this->dispatcher);
        $second = $this->controller->exit($request, $this->entityManager, $this->dispatcher);

        self::assertSame(['status' => 'ok'], json_decode((string) $first->getContent(), true, 512, JSON_THROW_ON_ERROR));
        self::assertSame(['status' => 'already_closed'], json_decode((string) $second->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function track(array $payload, string $remoteAddress = '203.0.113.87'): \Symfony\Component\HttpFoundation\JsonResponse
    {
        $request = new Request(
            server: ['REMOTE_ADDR' => $remoteAddress],
            content: json_encode($payload, JSON_THROW_ON_ERROR),
        );

        return $this->controller->track($request, $this->entityManager, $this->dispatcher);
    }
}
