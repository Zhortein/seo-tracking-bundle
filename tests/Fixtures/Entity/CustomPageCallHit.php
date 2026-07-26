<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Zhortein\SeoTrackingBundle\Entity\PageCallHitInterface;
use Zhortein\SeoTrackingBundle\Entity\PageCallHitTrait;

#[ORM\Entity]
#[ORM\Table(name: 'test_custom_page_call_hit')]
class CustomPageCallHit implements PageCallHitInterface
{
    use PageCallHitTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;
}
