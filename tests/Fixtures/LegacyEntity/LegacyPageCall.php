<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Fixtures\LegacyEntity;

use Doctrine\ORM\Mapping as ORM;
use Zhortein\SeoTrackingBundle\Entity\PageCallInterface;
use Zhortein\SeoTrackingBundle\Entity\PageCallTrait;

#[ORM\Entity]
#[ORM\Table(name: 'test_legacy_page_call')]
class LegacyPageCall implements PageCallInterface
{
    use PageCallTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
