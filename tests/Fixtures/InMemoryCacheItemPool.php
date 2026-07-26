<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Fixtures;

use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

final class InMemoryCacheItemPool implements CacheItemPoolInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $values = [];

    public ?int $lastTtl = null;
    public bool $failReads = false;
    public bool $failWrites = false;

    public function getItem(string $key): CacheItemInterface
    {
        if ($this->failReads) {
            throw new \RuntimeException('Cache read failed.');
        }

        return new InMemoryCacheItem($key, $this->values[$key] ?? null, array_key_exists($key, $this->values));
    }

    /**
     * @param list<string> $keys
     *
     * @return iterable<string, CacheItemInterface>
     */
    public function getItems(array $keys = []): iterable
    {
        foreach ($keys as $key) {
            yield $key => $this->getItem($key);
        }
    }

    public function hasItem(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }

    public function clear(): bool
    {
        $this->values = [];

        return true;
    }

    public function deleteItem(string $key): bool
    {
        unset($this->values[$key]);

        return true;
    }

    /**
     * @param list<string> $keys
     */
    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            $this->deleteItem($key);
        }

        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        if ($this->failWrites) {
            throw new \RuntimeException('Cache write failed.');
        }

        $this->values[$item->getKey()] = $item->get();
        $this->lastTtl = $item instanceof InMemoryCacheItem ? $item->ttl() : null;

        return true;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->save($item);
    }

    public function commit(): bool
    {
        return true;
    }

    public function count(): int
    {
        return count($this->values);
    }
}
