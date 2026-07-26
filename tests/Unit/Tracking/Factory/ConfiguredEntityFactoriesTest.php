<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Unit\Tracking\Factory;

use PHPUnit\Framework\TestCase;
use Zhortein\SeoTrackingBundle\Entity\PageCallHitInterface;
use Zhortein\SeoTrackingBundle\Entity\PageCallInterface;
use Zhortein\SeoTrackingBundle\Tracking\Factory\ConfiguredPageCallFactory;
use Zhortein\SeoTrackingBundle\Tracking\Factory\ConfiguredPageCallHitFactory;

final class ConfiguredEntityFactoriesTest extends TestCase
{
    public function testTheyInstantiateTheConfiguredClasses(): void
    {
        $pageCallClass = new class implements PageCallInterface {
        };
        $hitClass = new class implements PageCallHitInterface {
        };

        self::assertInstanceOf(
            $pageCallClass::class,
            (new ConfiguredPageCallFactory($pageCallClass::class))->create(),
        );
        self::assertInstanceOf(
            $hitClass::class,
            (new ConfiguredPageCallHitFactory($hitClass::class))->create(),
        );
    }
}
