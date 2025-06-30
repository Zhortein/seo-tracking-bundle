<?php

namespace Zhortein\SeoTrackingBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Zhortein\SeoTrackingBundle\Repository\PageCallRepository;

#[ORM\Entity(repositoryClass: PageCallRepository::class)]
#[ORM\Table(name: 'seo_page_call')]
#[ORM\Index(name: 'seo_page_call_idx', columns: ['url', 'campaign', 'medium', 'source', 'term', 'content'])]
#[ORM\UniqueConstraint(name: 'seo_page_call_uq', columns: ['url', 'campaign', 'medium', 'source', 'term', 'content'])]
#[UniqueEntity(fields: ['url', 'campaign', 'medium', 'source', 'term', 'content'])]
class PageCall
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $url = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $route = null;

    #[ORM\Column(nullable: true)]
    private ?array $routeArgs = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $campaign = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $medium = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $source = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $term = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $content = null;

    #[ORM\Column]
    private ?int $nbCalls = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $lastCalledAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $firstCalledAt = null;

    /**
     * @var Collection<int, PageCallHit>
     */
    #[ORM\OneToMany(targetEntity: PageCallHit::class, mappedBy: 'pageCall', orphanRemoval: true)]
    private Collection $hits;

    public function __construct()
    {
        $this->hits = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function getRoute(): ?string
    {
        return $this->route;
    }

    public function setRoute(?string $route): static
    {
        $this->route = $route;

        return $this;
    }

    public function getRouteArgs(): ?array
    {
        return $this->routeArgs;
    }

    public function setRouteArgs(?array $routeArgs): static
    {
        $this->routeArgs = $routeArgs;

        return $this;
    }

    public function getCampaign(): ?string
    {
        return $this->campaign;
    }

    public function setCampaign(?string $campaign): static
    {
        $this->campaign = $campaign;

        return $this;
    }

    public function getMedium(): ?string
    {
        return $this->medium;
    }

    public function setMedium(?string $medium): static
    {
        $this->medium = $medium;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getTerm(): ?string
    {
        return $this->term;
    }

    public function setTerm(?string $term): static
    {
        $this->term = $term;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getNbCalls(): ?int
    {
        return $this->nbCalls;
    }

    public function setNbCalls(int $nbCalls): static
    {
        $this->nbCalls = $nbCalls;

        return $this;
    }

    public function getLastCalledAt(): ?\DateTime
    {
        return $this->lastCalledAt;
    }

    public function setLastCalledAt(?\DateTime $lastCalledAt): static
    {
        $this->lastCalledAt = $lastCalledAt;

        return $this;
    }

    public function getFirstCalledAt(): ?\DateTimeImmutable
    {
        return $this->firstCalledAt;
    }

    public function setFirstCalledAt(\DateTimeImmutable $firstCalledAt): static
    {
        $this->firstCalledAt = $firstCalledAt;

        return $this;
    }

    /**
     * @return Collection<int, PageCallHit>
     */
    public function getHits(): Collection
    {
        return $this->hits;
    }

    public function addHit(PageCallHit $hit): static
    {
        if (!$this->hits->contains($hit)) {
            $this->hits->add($hit);
            $hit->setPageCall($this);
        }

        return $this;
    }

    public function removeHit(PageCallHit $hit): static
    {
        if ($this->hits->removeElement($hit)) {
            // set the owning side to null (unless already changed)
            if ($hit->getPageCall() === $this) {
                $hit->setPageCall(null);
            }
        }

        return $this;
    }
}
