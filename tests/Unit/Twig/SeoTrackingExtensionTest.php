<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Unit\Twig;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Zhortein\SeoTrackingBundle\Twig\SeoTrackingExtension;

final class SeoTrackingExtensionTest extends TestCase
{
    public function testTrackingAttributesContainRouteArgumentsAndType(): void
    {
        $request = Request::create('/article/test');
        $request->attributes->set('_route', 'article_show');
        $request->attributes->set('_route_params', ['slug' => 'test']);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $attributes = (new SeoTrackingExtension($requestStack))->seoTracking('article');

        self::assertStringContainsString('data-controller="zhortein--seo-tracking-bundle--tracking"', $attributes);
        self::assertStringContainsString('article_show', $attributes);
        self::assertStringContainsString('{&quot;slug&quot;:&quot;test&quot;}', $attributes);
        self::assertStringContainsString('tracking-type-value="article"', $attributes);
    }
}
