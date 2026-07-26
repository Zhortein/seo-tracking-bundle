<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\RouterInterface;
use Zhortein\SeoTrackingBundle\Tests\Fixtures\TestKernel;

final class BundleBootTest extends TestCase
{
    #[RunInSeparateProcess]
    public function testBundleBootsAndLoadsItsRoutes(): void
    {
        $kernel = new TestKernel('test', true);
        $kernel->boot();

        try {
            $container = $kernel->getContainer();
            self::assertTrue($container->has('router'));

            $router = $container->get('router');
            self::assertInstanceOf(RouterInterface::class, $router);
            self::assertSame(
                '/zhortein/seo-tracking/page-call/track',
                $router->generate('seo_tracking_page_call'),
            );
            self::assertSame(
                '/zhortein/seo-tracking/page-call/exit',
                $router->generate('seo_tracking_page_exit'),
            );
        } finally {
            $kernel->shutdown();
        }
    }
}
