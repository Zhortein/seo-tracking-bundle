<?php

declare(strict_types=1);

namespace Zhortein\SeoTrackingBundle\Tests\Fixtures\LegacyEntity;

use Doctrine\ORM\Mapping as ORM;
use Zhortein\SeoTrackingBundle\Entity\PageCallHitInterface;
use Zhortein\SeoTrackingBundle\Entity\PageCallInterface;

#[ORM\Entity]
#[ORM\Table(name: 'test_legacy_page_call_hit')]
class LegacyPageCallHit implements PageCallHitInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'hits')]
    #[ORM\JoinColumn(nullable: false)]
    private ?PageCallInterface $pageCall = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $calledAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $exitedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $durationSeconds = null;

    #[ORM\Column(options: ['default' => false])]
    private bool $bot = false;

    #[ORM\Column(nullable: true)]
    private ?string $pageType = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $language = null;

    public function setPageCall(PageCallInterface $pageCall): self
    {
        $this->pageCall = $pageCall;

        return $this;
    }

    public function setCalledAt(\DateTimeImmutable $calledAt): self
    {
        $this->calledAt = $calledAt;

        return $this;
    }

    public function setBot(bool $bot): self
    {
        $this->bot = $bot;

        return $this;
    }
}
