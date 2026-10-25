<?php

namespace App\Entity;

use App\Enum\AccountStatus;
use App\Enum\Platform;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Un solo account per utente e per piattaforma. Il token e' inserito a mano dall'utente. */
#[ORM\Entity]
#[ORM\Table(name: 'social_accounts')]
#[ORM\UniqueConstraint(name: 'uniq_user_platform', columns: ['user_id', 'platform'])]
class SocialAccount
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 16, enumType: Platform::class)]
    private Platform $platform;

    #[ORM\Column(length: 255)]
    private string $externalAccountId;

    #[ORM\Column(type: Types::TEXT)]
    private string $accessToken;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $tokenExpiresAt = null;

    #[ORM\Column(length: 16, enumType: AccountStatus::class)]
    private AccountStatus $status = AccountStatus::Active;

    public function __construct(User $user, Platform $platform, string $externalAccountId, string $accessToken)
    {
        $this->user = $user;
        $this->platform = $platform;
        $this->externalAccountId = $externalAccountId;
        $this->accessToken = $accessToken;
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getPlatform(): Platform { return $this->platform; }
    public function getExternalAccountId(): string { return $this->externalAccountId; }
    public function getAccessToken(): string { return $this->accessToken; }
    public function getStatus(): AccountStatus { return $this->status; }
    public function getTokenExpiresAt(): ?\DateTimeImmutable { return $this->tokenExpiresAt; }

    /** Nuovo token inserito dall'utente: l'account torna attivo. */
    public function replaceToken(string $externalAccountId, string $accessToken, ?\DateTimeImmutable $expiresAt): void
    {
        $this->externalAccountId = $externalAccountId;
        $this->accessToken = $accessToken;
        $this->tokenExpiresAt = $expiresAt;
        $this->status = AccountStatus::Active;
    }

    public function markNeedsReauth(): void { $this->status = AccountStatus::NeedsReauth; }

    /** Il token non viene mai esposto dall'API. */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'platform' => $this->platform->value,
            'externalAccountId' => $this->externalAccountId,
            'status' => $this->status->value,
            'tokenExpiresAt' => \App\Http\Dt::iso($this->tokenExpiresAt),
        ];
    }
}
