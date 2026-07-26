<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tracking\Request;

use Symfony\Component\HttpFoundation\Request;

final readonly class TrackingPayloadFactory
{
    public function fromRequest(Request $request): TrackingPayload
    {
        $data = $this->object(json_decode($request->getContent(), true, 512, JSON_THROW_ON_ERROR), 'JSON payload');

        $url = $this->requiredUrl($data, 'url');
        $canonicalUrl = $this->optionalUrl($data, 'canonicalUrl');
        $screen = $this->object($data['screen'] ?? [], '"screen" field');

        $routeArgs = $data['routeArgs'] ?? null;
        if (null !== $routeArgs) {
            $routeArgs = $this->object($routeArgs, '"routeArgs" field');
        }

        return new TrackingPayload(
            $url,
            $canonicalUrl,
            $this->optionalString($data, 'route', 255),
            $routeArgs,
            $this->optionalString($data, 'campaign', 255),
            $this->optionalString($data, 'medium', 255),
            $this->optionalString($data, 'source', 255),
            $this->optionalString($data, 'term', 255),
            $this->optionalString($data, 'content', 255),
            $this->optionalString($data, 'language', 10),
            $this->optionalPositiveInt($screen, 'width'),
            $this->optionalPositiveInt($screen, 'height'),
            $this->optionalIdentifier($data, 'parentHitId'),
            $this->optionalString($data, 'title', 255),
            $this->optionalString($data, 'type', 255),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function requiredUrl(array $data, string $key): string
    {
        $url = $this->optionalUrl($data, $key);
        if (null === $url) {
            throw new \InvalidArgumentException(sprintf('The "%s" field is required.', $key));
        }

        return $url;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function optionalUrl(array $data, string $key): ?string
    {
        $url = $this->optionalString($data, $key, 2048);
        if (null === $url) {
            return null;
        }

        $parts = parse_url($url);
        if (false === $parts || !isset($parts['scheme']) || !in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            throw new \InvalidArgumentException(sprintf('The "%s" field must be an absolute HTTP(S) URL.', $key));
        }

        return $url;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function optionalString(array $data, string $key, int $maxLength): ?string
    {
        $value = $data[$key] ?? null;
        if (null === $value || '' === $value) {
            return null;
        }

        if (!is_string($value)) {
            throw new \InvalidArgumentException(sprintf('The "%s" field must be a string.', $key));
        }

        if (mb_strlen($value) > $maxLength) {
            throw new \InvalidArgumentException(sprintf('The "%s" field is too long.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function optionalPositiveInt(array $data, string $key): ?int
    {
        $value = $data[$key] ?? null;
        if (null === $value) {
            return null;
        }

        if (!is_int($value) || $value < 0) {
            throw new \InvalidArgumentException(sprintf('The "%s" field must be a positive integer.', $key));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function optionalIdentifier(array $data, string $key): int|string|null
    {
        $value = $data[$key] ?? null;
        if (null === $value || '' === $value) {
            return null;
        }

        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value)) {
            return $value;
        }

        throw new \InvalidArgumentException(sprintf('The "%s" field must be a positive integer or a non-empty string.', $key));
    }

    /**
     * @return array<string, mixed>
     */
    private function object(mixed $value, string $field): array
    {
        if (!is_array($value)) {
            throw new \InvalidArgumentException(sprintf('The %s must be an object.', $field));
        }

        $object = [];
        foreach ($value as $key => $item) {
            if (!is_string($key)) {
                throw new \InvalidArgumentException(sprintf('The %s must be an object.', $field));
            }

            $object[$key] = $item;
        }

        return $object;
    }
}
