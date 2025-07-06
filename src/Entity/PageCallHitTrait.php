<?php

namespace Zhortein\SeoTrackingBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

trait PageCallHitTrait
{
    #[ORM\ManyToOne(inversedBy: 'hits')]
    #[ORM\JoinColumn(nullable: false)]
    private ?PageCallInterface $pageCall = null;

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

    #[ORM\ManyToOne(targetEntity: self::class)]
    #[ORM\JoinColumn(name: 'parent_hit_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?self $parentHit = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $bot = false;

    #[ORM\Column(nullable: true)]
    private ?int $delaySincePreviousHit = null;

    #[ORM\Column(nullable: true)]
    private ?string $pageTitle = null;

    #[ORM\Column(nullable: true)]
    private ?string $pageType = null;

    public function updateDuration(): void
    {
        if ($this->calledAt && $this->exitedAt) {
            $this->durationSeconds = $this->exitedAt->getTimestamp() - $this->calledAt->getTimestamp();
        }

        if (null !== $this->parentHit && $this->parentHit->getCalledAt() && $this->calledAt) {
            $delay = $this->calledAt->getTimestamp() - $this->parentHit->getCalledAt()->getTimestamp();
            $this->delaySincePreviousHit = max(0, $delay);
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPageCall(): ?PageCallInterface
    {
        return $this->pageCall;
    }

    public function setPageCall(?PageCallInterface $pageCall): self
    {
        $this->pageCall = $pageCall;

        return $this;
    }

    public function getReferrer(): ?string
    {
        return $this->referrer;
    }

    public function setReferrer(string $referrer): self
    {
        $this->referrer = $referrer;

        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    public function getAnonymizedIp(): ?string
    {
        return $this->anonymizedIp;
    }

    public function setAnonymizedIp(?string $anonymizedIp): self
    {
        $this->anonymizedIp = $anonymizedIp;

        return $this;
    }

    public function getCalledAt(): ?\DateTimeImmutable
    {
        return $this->calledAt;
    }

    public function setCalledAt(?\DateTimeImmutable $calledAt): self
    {
        $this->calledAt = $calledAt;

        return $this;
    }

    public function getExitedAt(): ?\DateTimeImmutable
    {
        return $this->exitedAt;
    }

    public function setExitedAt(?\DateTimeImmutable $exitedAt): self
    {
        $this->exitedAt = $exitedAt;

        return $this;
    }

    public function getDurationSeconds(): ?int
    {
        return $this->durationSeconds;
    }

    public function setDurationSeconds(?int $durationSeconds): self
    {
        $this->durationSeconds = $durationSeconds;

        return $this;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(?string $language): self
    {
        $this->language = $language;

        return $this;
    }

    public function getScreenWidth(): ?int
    {
        return $this->screenWidth;
    }

    public function setScreenWidth(?int $screenWidth): self
    {
        $this->screenWidth = $screenWidth;

        return $this;
    }

    public function getScreenHeight(): ?int
    {
        return $this->screenHeight;
    }

    public function setScreenHeight(?int $screenHeight): self
    {
        $this->screenHeight = $screenHeight;

        return $this;
    }

    public function getParentHit(): ?self
    {
        return $this->parentHit;
    }

    public function setParentHit(?self $parentHit): self
    {
        $this->parentHit = $parentHit;

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

    public function getDelaySincePreviousHit(): ?int
    {
        return $this->delaySincePreviousHit;
    }

    public function setDelaySincePreviousHit(?int $delaySincePreviousHit): self
    {
        $this->delaySincePreviousHit = $delaySincePreviousHit;

        return $this;
    }

    public function getPageTitle(): ?string
    {
        return $this->pageTitle;
    }

    public function setPageTitle(?string $pageTitle): self
    {
        $this->pageTitle = $pageTitle;

        return $this;
    }

    public function getPageType(): ?string
    {
        return $this->pageType;
    }

    public function setPageType(?string $pageType): self
    {
        $this->pageType = $pageType;

        return $this;
    }
}
