<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Journey;

use Zhortein\SeoTrackingBundle\Journey\DataSource\JourneyDataSourceInterface;
use Zhortein\SeoTrackingBundle\Journey\DTO\JourneyHitObservation;
use Zhortein\SeoTrackingBundle\Journey\DTO\JourneyNode;
use Zhortein\SeoTrackingBundle\Journey\DTO\JourneyPath;
use Zhortein\SeoTrackingBundle\Journey\DTO\JourneyReport;
use Zhortein\SeoTrackingBundle\Journey\DTO\JourneyStep;
use Zhortein\SeoTrackingBundle\Journey\DTO\JourneySummary;
use Zhortein\SeoTrackingBundle\Journey\DTO\JourneyTransition;
use Zhortein\SeoTrackingBundle\Journey\Filter\JourneyFilter;

final readonly class JourneyProvider implements JourneyProviderInterface
{
    public function __construct(private JourneyDataSourceInterface $dataSource)
    {
    }

    public function report(
        ?JourneyFilter $filter = null,
        int $transitionLimit = 20,
        int $pathLimit = 20,
        int $maxDepth = 25,
    ): JourneyReport {
        $this->assertLimit($transitionLimit, 'transition');
        $this->assertLimit($pathLimit, 'path');
        if ($maxDepth < 2 || $maxDepth > 100) {
            throw new \InvalidArgumentException('The journey maximum depth must be between 2 and 100.');
        }

        $filter ??= new JourneyFilter();
        /** @var array<string, JourneyHitObservation> $observations */
        $observations = [];
        /** @var list<string> $orderedKeys */
        $orderedKeys = [];
        $linkedHits = 0;
        $orphanedLinks = 0;
        /** @var array<string, array{from: JourneyNode, to: JourneyNode, count: int}> $transitions */
        $transitions = [];

        foreach ($this->dataSource->observations($filter) as $observation) {
            $key = $this->identifierKey($observation->id);
            if (isset($observations[$key])) {
                throw new \LogicException(sprintf('The journey data source returned hit "%s" more than once.', $observation->id));
            }

            $observations[$key] = $observation;
            $orderedKeys[] = $key;

            if (null === $observation->parentId) {
                continue;
            }

            ++$linkedHits;
            if (null === $observation->parentNode) {
                ++$orphanedLinks;
                continue;
            }

            $transitionKey = $observation->parentNode->key().':'.$observation->node->key();
            if (!isset($transitions[$transitionKey])) {
                $transitions[$transitionKey] = [
                    'from' => $observation->parentNode,
                    'to' => $observation->node,
                    'count' => 0,
                ];
            }
            ++$transitions[$transitionKey]['count'];
        }

        /** @var array<string, list<string>> $children */
        $children = array_fill_keys($orderedKeys, []);
        /** @var list<string> $roots */
        $roots = [];
        foreach ($orderedKeys as $key) {
            $observation = $observations[$key];
            if (null === $observation->parentId) {
                $roots[] = $key;
                continue;
            }

            $parentKey = $this->identifierKey($observation->parentId);
            if (!isset($observations[$parentKey])) {
                $roots[] = $key;
                continue;
            }

            $children[$parentKey][] = $key;
        }

        $this->sortKeys($roots, $observations);
        foreach ($children as &$childKeys) {
            $this->sortKeys($childKeys, $observations);
        }
        unset($childKeys);

        /** @var list<JourneyPath> $paths */
        $paths = [];
        $pathCount = 0;
        $maxObservedDepth = 0;
        $cyclicPaths = 0;
        $truncatedPaths = 0;
        /** @var array<string, true> $covered */
        $covered = [];
        $fragments = 0;

        foreach ($roots as $rootKey) {
            ++$fragments;
            $this->walk(
                $rootKey,
                [],
                [],
                $observations,
                $children,
                $pathLimit,
                $maxDepth,
                $paths,
                $pathCount,
                $maxObservedDepth,
                $cyclicPaths,
                $truncatedPaths,
                $covered,
            );
        }

        foreach ($orderedKeys as $key) {
            if (isset($covered[$key])) {
                continue;
            }

            ++$fragments;
            $this->walk(
                $key,
                [],
                [],
                $observations,
                $children,
                $pathLimit,
                $maxDepth,
                $paths,
                $pathCount,
                $maxObservedDepth,
                $cyclicPaths,
                $truncatedPaths,
                $covered,
            );
        }

        return new JourneyReport(
            $filter,
            new JourneySummary(
                count($observations),
                $linkedHits,
                $fragments,
                $pathCount,
                count($paths),
                $maxObservedDepth,
                $orphanedLinks,
                $cyclicPaths,
                $truncatedPaths,
            ),
            $this->rankTransitions($transitions, $transitionLimit),
            $paths,
        );
    }

    private function assertLimit(int $limit, string $name): void
    {
        if ($limit < 1 || $limit > 100) {
            throw new \InvalidArgumentException(sprintf('The journey %s limit must be between 1 and 100.', $name));
        }
    }

    /**
     * @param list<string>                            $pathKeys
     * @param array<string, true>                     $visiting
     * @param array<string, JourneyHitObservation>    $observations
     * @param array<string, list<string>>             $children
     * @param list<JourneyPath>                       $paths
     * @param array<string, true>                     $covered
     */
    private function walk(
        string $key,
        array $pathKeys,
        array $visiting,
        array $observations,
        array $children,
        int $pathLimit,
        int $maxDepth,
        array &$paths,
        int &$pathCount,
        int &$maxObservedDepth,
        int &$cyclicPaths,
        int &$truncatedPaths,
        array &$covered,
    ): void {
        $cyclic = isset($visiting[$key]);
        if (!$cyclic) {
            $pathKeys[] = $key;
            $visiting[$key] = true;
            $covered[$key] = true;
        }

        $depth = count($pathKeys);
        $maxObservedDepth = max($maxObservedDepth, $depth);
        $truncated = !$cyclic && $depth >= $maxDepth && [] !== $children[$key];
        if ($cyclic || $truncated || [] === $children[$key]) {
            ++$pathCount;
            if ($cyclic) {
                ++$cyclicPaths;
            }
            if ($truncated) {
                ++$truncatedPaths;
            }
            if (count($paths) < $pathLimit) {
                $paths[] = $this->path($pathKeys, $observations, $cyclic, $truncated);
            }

            return;
        }

        foreach ($children[$key] as $childKey) {
            $this->walk(
                $childKey,
                $pathKeys,
                $visiting,
                $observations,
                $children,
                $pathLimit,
                $maxDepth,
                $paths,
                $pathCount,
                $maxObservedDepth,
                $cyclicPaths,
                $truncatedPaths,
                $covered,
            );
        }
    }

    /**
     * @param list<string>                         $keys
     * @param array<string, JourneyHitObservation> $observations
     */
    private function path(array $keys, array $observations, bool $cyclic, bool $truncated): JourneyPath
    {
        if ([] === $keys) {
            throw new \LogicException('A journey path must contain at least one observed hit.');
        }

        $first = $observations[$keys[0]];
        /** @var list<JourneyStep> $steps */
        $steps = [];
        $knownPredecessor = null !== $first->parentId && null !== $first->parentNode
            && !isset($observations[$this->identifierKey($first->parentId)]);

        if ($knownPredecessor) {
            $steps[] = new JourneyStep(
                $first->parentId,
                $first->parentNode,
                $first->parentCalledAt,
                false,
            );
        }

        foreach ($keys as $key) {
            $observation = $observations[$key];
            $steps[] = new JourneyStep(
                $observation->id,
                $observation->node,
                $observation->calledAt,
                true,
            );
        }

        return new JourneyPath($steps, $knownPredecessor, $cyclic, $truncated);
    }

    /**
     * @param list<string>                         $keys
     * @param array<string, JourneyHitObservation> $observations
     */
    private function sortKeys(array &$keys, array $observations): void
    {
        usort($keys, static function (string $left, string $right) use ($observations): int {
            $leftDate = $observations[$left]->calledAt?->getTimestamp() ?? PHP_INT_MAX;
            $rightDate = $observations[$right]->calledAt?->getTimestamp() ?? PHP_INT_MAX;
            $byDate = $leftDate <=> $rightDate;

            return 0 !== $byDate ? $byDate : strnatcasecmp($left, $right);
        });
    }

    /**
     * @param array<string, array{from: JourneyNode, to: JourneyNode, count: int}> $transitions
     *
     * @return list<JourneyTransition>
     */
    private function rankTransitions(array $transitions, int $limit): array
    {
        uasort($transitions, static function (array $left, array $right): int {
            $byCount = $right['count'] <=> $left['count'];
            if (0 !== $byCount) {
                return $byCount;
            }

            $byFrom = strnatcasecmp($left['from']->label, $right['from']->label);

            return 0 !== $byFrom ? $byFrom : strnatcasecmp($left['to']->label, $right['to']->label);
        });

        $ranked = [];
        foreach (array_slice($transitions, 0, $limit, true) as $transition) {
            $ranked[] = new JourneyTransition(
                $transition['from'],
                $transition['to'],
                $transition['count'],
            );
        }

        return $ranked;
    }

    private function identifierKey(int|string $identifier): string
    {
        return (is_int($identifier) ? 'i:' : 's:').$identifier;
    }
}
