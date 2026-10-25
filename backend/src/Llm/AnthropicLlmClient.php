<?php

namespace App\Llm;

use App\Llm\Exception\LlmPermanentException;
use App\Llm\Exception\LlmTransientException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class AnthropicLlmClient implements LlmClientInterface
{
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
            $response = $this->http->request('POST', 'https://api.anthropic.com/v1/messages', [
                'timeout' => $this->timeout,
                'max_duration' => $this->timeout,
                'headers' => ['x-api-key' => $this->apiKey, 'anthropic-version' => '2023-06-01'],
                'json' => [
                    'model' => $this->model,
                    'max_tokens' => (int) ($params['max_tokens'] ?? 1024),
                    'messages' => [['role' => 'user', 'content' => $userText]],
                ],
            ]);
            $status = $response->getStatusCode();
            $data = $response->toArray(false);
        } catch (TransportExceptionInterface $e) {
            throw new LlmTransientException('Provider LLM non raggiungibile o in timeout', 0, $e);
        }

        if ($status === 429 || $status >= 500) {
            throw new LlmTransientException("Provider LLM temporaneamente non disponibile (HTTP $status)");
        }
        if ($status >= 400) {
            throw new LlmPermanentException("Richiesta rifiutata dal provider LLM (HTTP $status)");
        }

        $text = '';
        foreach ($data['content'] ?? [] as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= $block['text'];
            }
        }
        if ($text === '') {
            throw new LlmPermanentException('Risposta LLM vuota');
        }

        return $text;
    }
}
