<?php

namespace Zhortein\SeoTrackingBundle\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

class SeoTrackingDataCollector extends DataCollector
{
    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $utm = [
            'campaign' => $request->query->get('utm_campaign'),
            'source' => $request->query->get('utm_source'),
            'medium' => $request->query->get('utm_medium'),
            'term' => $request->query->get('utm_term'),
            'content' => $request->query->get('utm_content'),
        ];

        $this->data = [
            'utm' => array_filter($utm),
            'route' => $request->attributes->get('_route'),
            'route_params' => $request->attributes->get('_route_params'),
        ];
    }

    public function getName(): string
    {
        return 'seo_tracking';
    }

    /**
     * @return array<string, string>
     */
    public function getUtm(): array
    {
        $utm = $this->data['utm'] ?? null;
        if (!is_array($utm)) {
            return [];
        }

        $result = [];
        foreach ($utm as $key => $value) {
            if (is_string($key) && is_string($value)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    public function getRoute(): ?string
    {
        $route = $this->data['route'] ?? null;

        return is_string($route) ? $route : null;
    }

    /**
     * @return array<array-key, mixed>
     */
    public function getRouteParams(): array
    {
        $routeParams = $this->data['route_params'] ?? null;

        return is_array($routeParams) ? $routeParams : [];
    }
}
