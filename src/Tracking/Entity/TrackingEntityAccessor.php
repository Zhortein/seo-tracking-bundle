<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tracking\Entity;

use Zhortein\SeoTrackingBundle\Entity\PageCallHitInterface;
use Zhortein\SeoTrackingBundle\Entity\PageCallInterface;
use Zhortein\SeoTrackingBundle\Tracking\Request\TrackingPayload;

/**
 * Keeps the historical, method-based entity contract in one replaceable service.
 *
 * The public entity interfaces intentionally remain marker interfaces so existing
 * custom entities are not broken by adding new interface methods in a minor release.
 */
class TrackingEntityAccessor
{
    public function initializePageCall(
        PageCallInterface $pageCall,
        TrackingPayload $payload,
        string $groupingKey,
        bool $bot,
        \DateTimeImmutable $calledAt,
    ): void {
        $this->invoke($pageCall, 'setUrl', $payload->groupingUrl());
        $this->invokeIfSupported($pageCall, 'setCanonicalUrl', $payload->canonicalUrl);
        $this->invokeIfSupported($pageCall, 'setGroupingKey', $groupingKey);
        $this->invoke($pageCall, 'setRoute', $payload->route);
        $this->invoke($pageCall, 'setRouteArgs', $payload->routeArgs);
        $this->invoke($pageCall, 'setCampaign', $payload->campaign);
        $this->invoke($pageCall, 'setMedium', $payload->medium);
        $this->invoke($pageCall, 'setSource', $payload->source);
        $this->invoke($pageCall, 'setTerm', $payload->term);
        $this->invoke($pageCall, 'setContent', $payload->content);
        $this->invoke($pageCall, 'setFirstCalledAt', $calledAt);
        $this->invoke($pageCall, 'setNbCalls', 0);
        $this->invoke($pageCall, 'setBot', $bot);
    }

    public function registerCall(PageCallInterface $pageCall, \DateTime $calledAt): void
    {
        $numberOfCalls = $this->invoke($pageCall, 'getNbCalls');
        if (null !== $numberOfCalls && !is_int($numberOfCalls)) {
            throw new \LogicException(sprintf('%s::getNbCalls() must return an integer or null.', $pageCall::class));
        }

        $this->invoke($pageCall, 'setNbCalls', ($numberOfCalls ?? 0) + 1);
        $this->invoke($pageCall, 'setLastCalledAt', $calledAt);
    }

    public function initializeHit(
        PageCallHitInterface $hit,
        PageCallInterface $pageCall,
        TrackingPayload $payload,
        ?string $referrer,
        ?string $userAgent,
        ?string $anonymizedIp,
        bool $bot,
        \DateTimeImmutable $calledAt,
    ): void {
        $this->invoke($hit, 'setPageCall', $pageCall);
        $this->invokeIfSupported($hit, 'setUrl', $payload->url);
        $this->invoke($hit, 'setReferrer', $referrer);
        $this->invoke($hit, 'setUserAgent', $userAgent);
        $this->invoke($hit, 'setAnonymizedIp', $anonymizedIp);
        $this->invoke($hit, 'setLanguage', $payload->language);
        $this->invoke($hit, 'setCalledAt', $calledAt);
        $this->invoke($hit, 'setPageTitle', $payload->title);
        $this->invoke($hit, 'setPageType', $payload->type);
        $this->invoke($hit, 'setScreenWidth', $payload->screenWidth);
        $this->invoke($hit, 'setScreenHeight', $payload->screenHeight);
        $this->invoke($hit, 'setBot', $bot);
    }

    public function setParentHit(PageCallHitInterface $hit, PageCallHitInterface $parentHit): void
    {
        $this->invoke($hit, 'setParentHit', $parentHit);
    }

    public function updateDuration(PageCallHitInterface $hit): void
    {
        $this->invoke($hit, 'updateDuration');
    }

    public function getHitId(PageCallHitInterface $hit): int|string|null
    {
        $id = $this->invoke($hit, 'getId');
        if (null !== $id && !is_int($id) && !is_string($id)) {
            throw new \LogicException(sprintf('%s::getId() must return an integer, string or null.', $hit::class));
        }

        return $id;
    }

    public function isHitClosed(PageCallHitInterface $hit): bool
    {
        $exitedAt = $this->invoke($hit, 'getExitedAt');
        if (null !== $exitedAt && !$exitedAt instanceof \DateTimeInterface) {
            throw new \LogicException(sprintf('%s::getExitedAt() must return a date or null.', $hit::class));
        }

        return null !== $exitedAt;
    }

    public function closeHit(PageCallHitInterface $hit, \DateTimeImmutable $exitedAt): void
    {
        $this->invoke($hit, 'setExitedAt', $exitedAt);
        $this->invoke($hit, 'updateDuration');
    }

    public function getPageCall(PageCallHitInterface $hit): PageCallInterface
    {
        $pageCall = $this->invoke($hit, 'getPageCall');
        if (!$pageCall instanceof PageCallInterface) {
            throw new \LogicException(sprintf('%s::getPageCall() must return a page call.', $hit::class));
        }

        return $pageCall;
    }

    private function invoke(object $entity, string $method, mixed ...$arguments): mixed
    {
        if (!is_callable([$entity, $method])) {
            throw new \LogicException(sprintf('Configured entity %s must provide a public %s() method or use a custom %s.', $entity::class, $method, self::class));
        }

        return $entity->{$method}(...$arguments);
    }

    private function invokeIfSupported(object $entity, string $method, mixed ...$arguments): void
    {
        if (is_callable([$entity, $method])) {
            $entity->{$method}(...$arguments);
        }
    }
}
