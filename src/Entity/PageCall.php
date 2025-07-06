<?php

namespace Zhortein\SeoTrackingBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Zhortein\SeoTrackingBundle\Repository\PageCallRepository;

#[ORM\Entity(repositoryClass: PageCallRepository::class)]
#[ORM\Table(name: 'seo_page_call')]
#[ORM\Index(name: 'seo_page_call_idx', columns: ['url', 'campaign', 'medium', 'source', 'term', 'content', 'bot'])]
#[ORM\UniqueConstraint(name: 'seo_page_call_uq', columns: ['url', 'campaign', 'medium', 'source', 'term', 'content', 'bot'])]
#[UniqueEntity(fields: ['url', 'campaign', 'medium', 'source', 'term', 'content', 'bot'])]
class PageCall implements PageCallInterface
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
