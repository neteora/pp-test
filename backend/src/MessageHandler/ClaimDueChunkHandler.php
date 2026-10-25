<?php

namespace App\MessageHandler;

use App\Enum\PostStatus;
use App\Message\ClaimDueChunk;
use App\Message\PublishPost;
use App\Service\PostTransitions;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

/** pending -> queued (atomico) e invio alla coda della piattaforma. */
#[AsMessageHandler]
final class ClaimDueChunkHandler
{
    public function __construct(
        private PostTransitions $posts,
        private MessageBusInterface $bus,
        private ClockInterface $clock,
    ) {}

    public function __invoke(ClaimDueChunk $message): void
    {
        $now = \DateTimeImmutable::createFromInterface($this->clock->now());
        foreach ($message->posts as [$id, $platform]) {
            // Vince una sola esecuzione; se l'utente ha annullato nel frattempo, il claim fallisce
            if ($this->posts->transition($id, PostStatus::Pending, PostStatus::Queued, dueBy: $now)) {
                $this->bus->dispatch(new PublishPost($id), [new TransportNamesStamp(['publish_'.$platform])]);
            }
        }
    }
}
