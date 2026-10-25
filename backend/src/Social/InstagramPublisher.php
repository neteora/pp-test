<?php

namespace App\Social;

use App\Entity\PostTarget;
use App\Enum\Platform;
use App\Social\Exception\AuthRejectedException;
use App\Social\Exception\PermanentPublishException;
use App\Social\Exception\PublishInProgressException;
use App\Social\Exception\TransientPublishException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Instagram Graph API: container (/media) -> attesa FINISHED -> /media_publish.
 * Prima versione: solo immagini JPEG. Il polling dello stato avviene a ogni retry del messaggio (1/min).
 * Niente token o risposte complete nei log.
 */
final class InstagramPublisher implements SocialPublisherInterface
{
    // Codici di errore Graph per rate limit / errori temporanei
    private const TRANSIENT_CODES = [1, 2, 4, 17, 32, 341, 613];

    public function __construct(
        private HttpClientInterface $http,
        private string $graphBase,
        private string $graphVersion,
    ) {}

    public function platform(): Platform
    {
        return Platform::Instagram;
    }

    public function publish(PostTarget $post): string
    {
        $account = $post->getSocialAccount();
        $igUserId = $account->getExternalAccountId();
        $token = $account->getAccessToken();
        $base = rtrim($this->graphBase, '/').'/'.$this->graphVersion;

        if ($post->getContainerId() === null) {
            $media = $post->getMediaAsset();
            if ($media === null || $media->getType() !== 'image/jpeg') {
                throw new PermanentPublishException('Instagram richiede un media: immagine JPEG');
            }
            $data = $this->call('POST', "$base/$igUserId/media", [
                'image_url' => $media->getPublicUrl(),
                'caption' => $post->getText(),
                'access_token' => $token,
            ]);
            $post->setContainerId((string) ($data['id'] ?? throw new TransientPublishException('Risposta inattesa')));
        }

        $status = $this->call('GET', $base.'/'.$post->getContainerId(), [
            'fields' => 'status_code',
            'access_token' => $token,
        ]);
        switch ($status['status_code'] ?? null) {
            case 'FINISHED':
                break;
            case 'IN_PROGRESS':
                throw new PublishInProgressException('Container in elaborazione');
            case 'ERROR':
            case 'EXPIRED':
                $post->setContainerId(null);
                throw new PermanentPublishException('Elaborazione del media fallita su Instagram');
            default:
                throw new TransientPublishException('Stato container sconosciuto');
        }

        $published = $this->call('POST', "$base/$igUserId/media_publish", [
            'creation_id' => $post->getContainerId(),
            'access_token' => $token,
        ]);

        return (string) ($published['id'] ?? throw new TransientPublishException('Risposta inattesa'));
    }

    /** @return array<string, mixed> */
    private function call(string $method, string $url, array $params): array
    {
        try {
            $options = $method === 'GET' ? ['query' => $params] : ['body' => $params];
            $response = $this->http->request($method, $url, $options + ['timeout' => 30]);
            $status = $response->getStatusCode();
            $data = $response->toArray(false);
        } catch (TransportExceptionInterface|\JsonException $e) {
            throw new TransientPublishException('Errore di rete verso Instagram', 0, $e);
        } catch (\Throwable $e) {
            if ($e instanceof \Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface
                || $e instanceof \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface) {
                throw new TransientPublishException('Risposta non valida da Instagram', 0, $e);
            }
            throw $e;
        }

        if ($status < 400) {
            return $data;
        }

        $code = (int) ($data['error']['code'] ?? 0);
        if ($status === 401 || $code === 190) {
            throw new AuthRejectedException('Token rifiutato da Instagram');
        }
        if ($status >= 500 || $status === 429 || in_array($code, self::TRANSIENT_CODES, true)) {
            throw new TransientPublishException("Instagram temporaneamente non disponibile (HTTP $status)");
        }

        $sub = (int) ($data['error']['error_subcode'] ?? 0);
        $msg = mb_substr((string) ($data['error']['error_user_msg'] ?? $data['error']['message'] ?? ''), 0, 200);

        throw new PermanentPublishException("Richiesta rifiutata da Instagram (HTTP $status, codice $code/$sub): $msg");
    }
}
