<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Fixtures;

use Psr\Cache\CacheItemInterface;

final class InMemoryCacheItem implements CacheItemInterface
{
    private mixed $value;
    private bool $hit;
    private ?int $ttl = null;

    public function __construct(
        private readonly string $key,
        mixed $value = null,
        bool $hit = false,
    ) {
        $this->value = $value;
        $this->hit = $hit;
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        return $this->value;
    }

    public function isHit(): bool
    {
        return $this->hit;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;
        $this->hit = true;

        return $this;
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        $this->ttl = null === $expiration ? null : max(0, $expiration->getTimestamp() - time());

        return $this;
    }

    public function expiresAfter(int|\DateInterval|null $time): static
    {
        if ($time instanceof \DateInterval) {
            $now = new \DateTimeImmutable();
            $this->ttl = $now->add($time)->getTimestamp() - $now->getTimestamp();
        } else {
            $this->ttl = $time;
        }

        return $this;
    }

    public function ttl(): ?int
    {
        return $this->ttl;
    }
}
