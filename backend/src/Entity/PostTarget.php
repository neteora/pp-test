<?php

namespace App\Entity;

use App\Enum\PostStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Un record per ogni coppia post x account social: testo adattato, media, stato, esito. */
#[ORM\Entity]
#[ORM\Table(name: 'post_targets')]
#[ORM\Index(name: 'idx_status_scheduled', columns: ['status', 'scheduled_at'])]
#[ORM\Index(name: 'idx_user_status_scheduled', columns: ['user_id', 'status', 'scheduled_at'])]
class PostTarget
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private SocialAccount $socialAccount;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?GenerationRequest $generationRequest = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?MediaAsset $mediaAsset = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $text;

    #[ORM\Column(length: 16, enumType: PostStatus::class)]
    private PostStatus $status = PostStatus::Draft;

    /** Sempre in UTC */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $scheduledAt = null;

    /** Id del post sulla piattaforma (idempotenza: se presente non si ripubblica) */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $externalId = null;

    /** Passo intermedio della piattaforma (es. container Instagram gia' creato) */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $containerId = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $lastError = null;

    #[ORM\Column(type: Types::SMALLINT)]
    private int $attempts = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(User $user, SocialAccount $account, string $text)
    {
        $this->user = $user;
        $this->socialAccount = $account;
        $this->text = $text;
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getSocialAccount(): SocialAccount { return $this->socialAccount; }
    public function getText(): string { return $this->text; }
    public function setText(string $t): void { $this->text = $t; $this->touch(); }
    public function getMediaAsset(): ?MediaAsset { return $this->mediaAsset; }
    public function setMediaAsset(?MediaAsset $m): void { $this->mediaAsset = $m; $this->touch(); }
    public function setGenerationRequest(?GenerationRequest $g): void { $this->generationRequest = $g; }
    public function setSocialAccount(SocialAccount $a): void { $this->socialAccount = $a; $this->touch(); }
    public function getStatus(): PostStatus { return $this->status; }
    public function setStatus(PostStatus $s): void { $this->status = $s; $this->touch(); }
    public function getScheduledAt(): ?\DateTimeImmutable { return $this->scheduledAt; }
    public function setScheduledAt(?\DateTimeImmutable $d): void { $this->scheduledAt = $d; $this->touch(); }
    public function getExternalId(): ?string { return $this->externalId; }
    public function setExternalId(?string $id): void { $this->externalId = $id; }
    public function getContainerId(): ?string { return $this->containerId; }
    public function setContainerId(?string $id): void { $this->containerId = $id; }
    public function getLastError(): ?string { return $this->lastError; }
    public function setLastError(?string $e): void { $this->lastError = $e === null ? null : mb_substr($e, 0, 500); }
    public function getAttempts(): int { return $this->attempts; }
    public function incrementAttempts(): void { ++$this->attempts; }

    private function touch(): void { $this->updatedAt = new \DateTimeImmutable(); }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'socialAccountId' => $this->socialAccount->getId(),
            'platform' => $this->socialAccount->getPlatform()->value,
            'text' => $this->text,
            'media' => $this->mediaAsset?->toArray(),
            'status' => $this->status->value,
            'scheduledAt' => \App\Http\Dt::iso($this->scheduledAt),
            'externalId' => $this->externalId,
            'lastError' => $this->lastError,
            'attempts' => $this->attempts,
            'createdAt' => \App\Http\Dt::iso($this->createdAt),
            'updatedAt' => \App\Http\Dt::iso($this->updatedAt),
        ];
    }
}
