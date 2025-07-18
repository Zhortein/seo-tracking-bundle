<?php

namespace Zhortein\SeoTrackingBundle\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

trait PageCallTrait
{
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
     * @var Collection<int, PageCallHitInterface>
     */
    #[ORM\OneToMany(targetEntity: PageCallHitInterface::class, mappedBy: 'pageCall', orphanRemoval: true)]
    private Collection $hits;

    #[ORM\Column(options: ['default' => false])]
    private bool $bot = false;

    public function __construct()
    {
        $this->hits = new ArrayCollection();
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getRoute(): ?string
    {
        return $this->route;
    }

    public function setRoute(?string $route): self
    {
        $this->route = $route;

        return $this;
    }

    public function getRouteArgs(): ?array
    {
        return $this->routeArgs;
    }

    public function setRouteArgs(?array $routeArgs): self
    {
        $this->routeArgs = $routeArgs;

        return $this;
    }

    public function getCampaign(): ?string
    {
        return $this->campaign;
    }

    public function setCampaign(?string $campaign): self
    {
        $this->campaign = $campaign;

        return $this;
    }

    public function getMedium(): ?string
    {
        return $this->medium;
    }

    public function setMedium(?string $medium): self
    {
        $this->medium = $medium;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function getTerm(): ?string
    {
        return $this->term;
    }

    public function setTerm(?string $term): self
    {
        $this->term = $term;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getNbCalls(): ?int
    {
        return $this->nbCalls;
    }

    public function setNbCalls(int $nbCalls): self
    {
        $this->nbCalls = $nbCalls;

        return $this;
    }

    public function getLastCalledAt(): ?\DateTime
    {
        return $this->lastCalledAt;
    }

    public function setLastCalledAt(?\DateTime $lastCalledAt): self
    {
        $this->lastCalledAt = $lastCalledAt;

        return $this;
    }

    public function getFirstCalledAt(): ?\DateTimeImmutable
    {
        return $this->firstCalledAt;
    }

    public function setFirstCalledAt(\DateTimeImmutable $firstCalledAt): self
    {
        $this->firstCalledAt = $firstCalledAt;

        return $this;
    }

    /**
     * @return Collection<int, PageCallHitInterface>
     */
    public function getHits(): Collection
    {
        return $this->hits;
    }

    public function addHit(PageCallHitInterface $hit): self
    {
        if (!$this->hits->contains($hit)) {
            $this->hits->add($hit);
            $hit->setPageCall($this);
        }

        return $this;
    }

    public function removeHit(PageCallHitInterface $hit): self
    {
        if ($this->hits->removeElement($hit)) {
            // set the owning side to null (unless already changed)
            if ($hit->getPageCall() === $this) {
                $hit->setPageCall(null);
            }
        }

        return $this;
    }

    public function isBot(): bool
    {
        return $this->bot;
    }

    public function setBot(bool $bot): self
    {
        $this->bot = $bot;

        return $this;
    }
}
