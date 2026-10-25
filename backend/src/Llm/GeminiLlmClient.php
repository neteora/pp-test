<?php

namespace App\Llm;

use App\Llm\Exception\LlmPermanentException;
use App\Llm\Exception\LlmTransientException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/** Adapter Google Gemini (Generative Language API). */
final class GeminiLlmClient implements LlmClientInterface
{
    private const BASE = 'https://generativelanguage.googleapis.com/v1beta/models';

    public function __construct(
        private HttpClientInterface $http,
        private string $apiKey,
        private string $model,
        private float $timeout,
    ) {}

    public function model(): string
    {
        return $this->model;
    }

    public function defaultParams(): array
    {
        return ['max_tokens' => 1024];
    }

    public function generate(string $userText, array $params): string
    {
        try {
            $response = $this->http->request('POST', sprintf('%s/%s:generateContent', self::BASE, $this->model), [
                'timeout' => $this->timeout,
                'max_duration' => $this->timeout,
                'headers' => ['x-goog-api-key' => $this->apiKey],
                'json' => [
                    'contents' => [['role' => 'user', 'parts' => [['text' => $userText]]]],
                    'generationConfig' => ['maxOutputTokens' => (int) ($params['max_tokens'] ?? 1024)],
                ],
            ]);
            $status = $response->getStatusCode();
            $data = $response->toArray(false);
        } catch (TransportExceptionInterface $e) {
            throw new LlmTransientException('Provider LLM non raggiungibile o in timeout: '.$e->getMessage(), 0, $e);
        }

        if ($status === 429 || $status >= 500) {
            throw new LlmTransientException("Provider LLM temporaneamente non disponibile (HTTP $status)");
        }
        if ($status >= 400) {
            throw new LlmPermanentException("Richiesta rifiutata dal provider LLM (HTTP $status)");
        }
        if (isset($data['promptFeedback']['blockReason'])) {
            throw new LlmPermanentException('Richiesta bloccata dal provider LLM');
        }

        $text = '';
        foreach ($data['candidates'][0]['content']['parts'] ?? [] as $part) {
            $text .= $part['text'] ?? '';
        }
        if ($text === '') {
            throw new LlmPermanentException('Risposta LLM vuota');
        }

        return $text;
    }
}
