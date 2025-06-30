<?php

namespace Zhortein\SeoTrackingBundle\Entity;

use Zhortein\SeoTrackingBundle\Repository\PageCallHitRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PageCallHitRepository::class)]
#[ORM\Table(name: 'seo_page_call_hit')]
class PageCallHit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'hits')]
    #[ORM\JoinColumn(nullable: false)]
    private ?PageCall $pageCall = null;

    #[ORM\Column(length: 512)]
    private ?string $referrer = null;

    #[ORM\Column(length: 512, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $anonymizedIp = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $calledAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $exitedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $durationSeconds = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $language = null;

    #[ORM\Column(nullable: true)]
    private ?int $screenWidth = null;

    #[ORM\Column(nullable: true)]
    private ?int $screenHeight = null;

    public function updateDuration(): void
    {
        if ($this->calledAt && $this->exitedAt) {
            $this->durationSeconds = $this->exitedAt->getTimestamp() - $this->calledAt->getTimestamp();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPageCall(): ?PageCall
    {
        return $this->pageCall;
    }

    public function setPageCall(?PageCall $pageCall): static
    {
        $this->pageCall = $pageCall;

        return $this;
    }

    public function getReferrer(): ?string
    {
        return $this->referrer;
    }

    public function setReferrer(string $referrer): static
    {
        $this->referrer = $referrer;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    public function getAnonymizedIp(): ?string
    {
        return $this->anonymizedIp;
    }

    public function setAnonymizedIp(?string $anonymizedIp): static
    {
        $this->anonymizedIp = $anonymizedIp;

        return $this;
    }

    public function getCalledAt(): ?\DateTimeImmutable
    {
        return $this->calledAt;
    }

    public function setCalledAt(?\DateTimeImmutable $calledAt): static
    {
        $this->calledAt = $calledAt;

        return $this;
    }

    public function getExitedAt(): ?\DateTimeImmutable
    {
        return $this->exitedAt;
    }

    public function setExitedAt(?\DateTimeImmutable $exitedAt): static
    {
        $this->exitedAt = $exitedAt;

        return $this;
    }

    public function getDurationSeconds(): ?int
    {
        return $this->durationSeconds;
    }

    public function setDurationSeconds(?int $durationSeconds): static
    {
        $this->durationSeconds = $durationSeconds;

        return $this;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(?string $language): static
    {
        $this->language = $language;
        return $this;
    }

    public function getScreenWidth(): ?int
    {
        return $this->screenWidth;
    }

    public function setScreenWidth(?int $screenWidth): static
    {
        $this->screenWidth = $screenWidth;
        return $this;
    }

    public function getScreenHeight(): ?int
    {
        return $this->screenHeight;
    }

    public function setScreenHeight(?int $screenHeight): static
    {
        $this->screenHeight = $screenHeight;
        return $this;
    }

}
