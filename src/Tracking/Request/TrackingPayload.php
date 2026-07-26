<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tracking\Request;

final readonly class TrackingPayload
{
    /**
     * @param array<string, mixed>|null                 $routeArgs
     * @param array<string, string|int|float|bool>|null $dimensions
     */
    public function __construct(
        public string $url,
        public ?string $canonicalUrl,
        public ?string $route,
        public ?array $routeArgs,
        public ?string $campaign,
        public ?string $medium,
        public ?string $source,
        public ?string $term,
        public ?string $content,
        public ?string $language,
        public ?int $screenWidth,
        public ?int $screenHeight,
        public int|string|null $parentHitId,
        public ?string $title,
        public ?string $type,
        public ?array $dimensions = null,
    ) {
    }

    public function groupingUrl(): string
    {
        return $this->canonicalUrl ?? $this->url;
    }

    /**
     * @return array{campaign: ?string, medium: ?string, source: ?string, term: ?string, content: ?string}
     */
    public function utm(): array
    {
        return [
            'campaign' => $this->campaign,
            'medium' => $this->medium,
            'source' => $this->source,
            'term' => $this->term,
            'content' => $this->content,
        ];
    }
}
