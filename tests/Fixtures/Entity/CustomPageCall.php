<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Fixtures\Entity;

use Doctrine\ORM\Mapping as ORM;
use Zhortein\SeoTrackingBundle\Entity\PageCallInterface;
use Zhortein\SeoTrackingBundle\Entity\PageCallTrait;

#[ORM\Entity]
#[ORM\Table(name: 'test_custom_page_call')]
class CustomPageCall implements PageCallInterface
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
