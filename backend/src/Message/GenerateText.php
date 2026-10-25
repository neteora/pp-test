<?php

namespace App\Message;

/** Il testo dell'utente resta solo nel messaggio (transitorio), non nel DB applicativo. */
final readonly class GenerateText
{
    public function __construct(public int $generationRequestId, public string $userText) {}
}
