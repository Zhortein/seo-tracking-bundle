<?php

namespace Zhortein\SeoTrackingBundle\DataCollector;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

class SeoTrackingDataCollector extends DataCollector
{
    public function collect(Request $request, Response $response, \Throwable $exception = null): void
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

    public function getUtm(): array
    {
        return $this->data['utm'] ?? [];
    }

    public function getRoute(): ?string
    {
        return $this->data['route'] ?? null;
    }

    public function getRouteParams(): array
    {
        return $this->data['route_params'] ?? [];
    }
}