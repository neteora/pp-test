<?php

namespace App\Social;

use App\Enum\Platform;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final class PublisherRegistry
{
    /** @var array<string, SocialPublisherInterface> */
    private array $byPlatform = [];

    /** @param iterable<SocialPublisherInterface> $publishers */
    public function __construct(#[AutowireIterator('app.social_publisher')] iterable $publishers)
    {
        foreach ($publishers as $p) {
            $this->byPlatform[$p->platform()->value] = $p;
        }
    }

    public function supports(Platform $platform): bool
    {
        return isset($this->byPlatform[$platform->value]);
    }

    public function for(Platform $platform): SocialPublisherInterface
    {
        return $this->byPlatform[$platform->value] ?? throw new \LogicException('Piattaforma non supportata');
    }

    /** @return string[] */
    public function platforms(): array
    {
        return array_keys($this->byPlatform);
    }
}
