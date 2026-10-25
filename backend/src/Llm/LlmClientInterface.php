<?php

namespace App\Llm;

/** Un adapter per provider. Modello, chiave e parametri sono configurazione. */
interface LlmClientInterface
{
    public function model(): string;

    /** @return array<string, mixed> parametri di default salvati sulla richiesta */
    public function defaultParams(): array;

    /**
     * Invia il testo dell'utente cosi' com'e'.
     *
     * @throws Exception\LlmTransientException  timeout, 429, 5xx
     * @throws Exception\LlmPermanentException  richiesta rifiutata
     */
    public function generate(string $userText, array $params): string;
}
