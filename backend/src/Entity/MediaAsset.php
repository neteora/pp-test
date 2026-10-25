<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'media_assets')]
class MediaAsset
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 255)]
    private string $storageKey;

    #[ORM\Column(length: 64)]
    private string $type;

    #[ORM\Column(type: Types::BIGINT)]
    private string $size;

    #[ORM\Column(length: 1024)]
    private string $publicUrl;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $user, string $storageKey, string $type, int $size, string $publicUrl)
    {
        $this->user = $user;
        $this->storageKey = $storageKey;
        $this->type = $type;
        $this->size = (string) $size;
        $this->publicUrl = $publicUrl;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getType(): string { return $this->type; }
    public function getPublicUrl(): string { return $this->publicUrl; }

    public function toArray(): array
    {
        return ['id' => $this->id, 'type' => $this->type, 'size' => (int) $this->size, 'publicUrl' => $this->publicUrl];
    }
}
