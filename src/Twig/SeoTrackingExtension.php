<?php

namespace Zhortein\SeoTrackingBundle\Twig;

use Symfony\Component\HttpFoundation\RequestStack;
use Twig\Attribute\AsTwigFunction;

final class SeoTrackingExtension
{
    public function __construct(private readonly RequestStack $requestStack)
    {
    }

    #[AsTwigFunction(name: 'seo_tracking', isSafe: ['html'])]
    public function seoTracking(string $type = 'generic'): string
    {
        $request = $this->requestStack->getCurrentRequest();
        $route = $request?->attributes->get('_route', '');
        $routeArgs = $request?->attributes->get('_route_params', []);

        $controller = 'zhortein--seo-tracking-bundle--tracking';

        $data = [
            'route' => $route,
            'routeArgs' => $routeArgs,
            'type' => $type,
        ];

        $controllerSafe = htmlspecialchars($controller, ENT_QUOTES);
        $attr = sprintf('data-controller="%s"', $controllerSafe);

        foreach ($data as $key => $value) {
            $attr .= sprintf(
                ' data-%s-%s-value=\'%s\'',
                str_replace('--', '-', $controllerSafe),
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