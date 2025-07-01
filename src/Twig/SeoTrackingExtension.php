<?php

namespace Zhortein\SeoTrackingBundle\Twig;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;
use Twig\Environment;

final readonly class SeoTrackingExtension
{
    public function __construct(private RequestStack $requestStack)
    {
    }

    #[AsTwigFunction(name: 'seo_tracking', needsEnvironment: true, isSafe: ['html'])]
    public function seoTracking(Environment $environment, string $type = 'generic'): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $route = $request?->attributes->get('_route', '');
        $routeArgs = $request?->attributes->get('_route_params', []);

        $controller = 'zhortein--seo-tracking-bundle--tracking';

        $attr = sprintf('data-controller="%s"', htmlspecialchars($controller, ENT_QUOTES));

        $data = [
            'route' => $route,
            'route-args' => $routeArgs,
            'type' => $type,
        ];

        foreach ($data as $key => $value) {
            $attr .= sprintf(
                ' data-%s-%s-value="%s"',
                $controller,
                $key,
                $this->encodeStimulusValue($value)
            );
        }

        return $attr;
    }

    private function encodeStimulusValue(mixed $value): string
    {
        try {
            return htmlspecialchars(
                is_array($value) || is_object($value)
                    ? json_encode($value, JSON_THROW_ON_ERROR)
                    : (string)$value,
                ENT_QUOTES
            );
        } catch (\Throwable) {
            return '';
        }
    }

}