<?php

namespace App\Entity;

use App\Enum\GenerationStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/** Il prompt dell'utente NON viene salvato: viaggia solo nel messaggio della coda. */
#[ORM\Entity]
#[ORM\Table(name: 'generation_requests')]
class GenerationRequest
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    /** Parametri di generazione (es. max_tokens, temperature) */
    #[ORM\Column(type: Types::JSON)]
    private array $params;

    #[ORM\Column(length: 100)]
    private string $model;

    #[ORM\Column(length: 16, enumType: GenerationStatus::class)]
    private GenerationStatus $status = GenerationStatus::Pending;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $resultText = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $error = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    public function __construct(User $user, string $model, array $params)
    {
        $this->user = $user;
        $this->model = $model;
        $this->params = $params;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function getParams(): array { return $this->params; }
    public function getStatus(): GenerationStatus { return $this->status; }
    public function setStatus(GenerationStatus $s): void { $this->status = $s; }

    public function complete(string $text): void
    {
        $this->status = GenerationStatus::Done;
        $this->resultText = $text;
        $this->error = null;
    }

    public function fail(string $error): void
    {
        $this->status = GenerationStatus::Failed;
        $this->error = mb_substr($error, 0, 500);
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'model' => $this->model,
            'resultText' => $this->resultText,
            'error' => $this->error,
            'createdAt' => \App\Http\Dt::iso($this->createdAt),
        ];
    }
}
