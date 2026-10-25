<?php

namespace App\MessageHandler;

use App\Message\CheckDuePosts;
use App\Message\ClaimDueChunk;
use App\Service\PostTransitions;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Tick dello Scheduler: legge i post "pending" scaduti e li distribuisce in chunk
 * sul transport async. Un chunk duplicato e' innocuo perche' il claim e' atomico.
 */
#[AsMessageHandler]
final class CheckDuePostsHandler
{
    private const SELECT_LIMIT = 5000;
    private const CHUNK_SIZE = 100;

    public function __construct(
        private PostTransitions $posts,
        private MessageBusInterface $bus,
        private ClockInterface $clock,
    ) {}

    public function __invoke(CheckDuePosts $message): void
    {
        $due = $this->posts->findDue(\DateTimeImmutable::createFromInterface($this->clock->now()), self::SELECT_LIMIT);
        foreach (array_chunk($due, self::CHUNK_SIZE) as $chunk) {
            $this->bus->dispatch(new ClaimDueChunk($chunk));
        }
    }
}
