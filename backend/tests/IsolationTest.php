<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Isolamento tra utenti: l'utente B non deve mai vedere o toccare i dati di A.
 * Richiede il database raggiungibile (docker compose) e le migrazioni applicate.
 */
final class IsolationTest extends WebTestCase
{
    private function api(KernelBrowser $c, string $method, string $uri, ?array $body = null, ?string $token = null): array
    {
        $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
        if ($token) {
            $server['HTTP_AUTHORIZATION'] = 'Bearer '.$token;
        }
        $c->request($method, $uri, [], [], $server, $body === null ? null : json_encode($body));

        return [$c->getResponse()->getStatusCode(), json_decode($c->getResponse()->getContent() ?: 'null', true)];
    }

    private function register(KernelBrowser $c, string $tag): string
    {
        [, $data] = $this->api($c, 'POST', '/api/auth/register', [
            'email' => $tag.'-'.bin2hex(random_bytes(4)).'@example.test',
            'password' => 'password-123',
        ]);

        return $data['token'];
    }

    public function testUserCannotSeeOrChangeAnotherUsersPosts(): void
    {
        $c = static::createClient();
        $a = $this->register($c, 'a');
        $b = $this->register($c, 'b');

        $this->api($c, 'PUT', '/api/social-accounts/instagram', ['externalAccountId' => '1', 'accessToken' => 'tok-a'], $a);
        [, $accounts] = $this->api($c, 'GET', '/api/social-accounts', null, $a);
        $accountId = $accounts['items'][0]['id'];

        [$status, $post] = $this->api($c, 'POST', '/api/posts', ['socialAccountId' => $accountId, 'text' => 'ciao'], $a);
        $this->assertSame(201, $status);
        $id = $post['id'];

        $this->assertSame(404, $this->api($c, 'GET', "/api/posts/$id", null, $b)[0]);
        $this->assertSame(404, $this->api($c, 'PATCH', "/api/posts/$id", ['text' => 'x'], $b)[0]);
        $this->assertSame(404, $this->api($c, 'POST', "/api/posts/$id/approve", null, $b)[0]);
        $this->assertSame(404, $this->api($c, 'DELETE', "/api/posts/$id", null, $b)[0]);
        $this->assertSame([], $this->api($c, 'GET', '/api/posts', null, $b)[1]['items']);

        // B non puo' usare l'account social di A
        $this->assertSame(400, $this->api($c, 'POST', '/api/posts', ['socialAccountId' => $accountId, 'text' => 'x'], $b)[0]);
        // Il token non viene mai esposto
        $this->assertArrayNotHasKey('accessToken', $accounts['items'][0]);
    }

    public function testApiRequiresLogin(): void
    {
        $c = static::createClient();
        $this->assertSame(401, $this->api($c, 'GET', '/api/posts')[0]);
    }
}
