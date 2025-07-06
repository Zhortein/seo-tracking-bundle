<?php

namespace Zhortein\SeoTrackingBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Zhortein\SeoTrackingBundle\Repository\PageCallHitRepository;

#[ORM\Entity(repositoryClass: PageCallHitRepository::class)]
#[ORM\Table(name: 'seo_page_call_hit')]
class PageCallHit implements PageCallHitInterface
{
    use PageCallHitTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }
}
