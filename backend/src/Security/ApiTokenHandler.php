<?php

namespace App\Security;

use App\Entity\ApiToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/** Token per-utente emesso dal login (nessun token statico condiviso). */
final class ApiTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(private EntityManagerInterface $em) {}

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        $token = $this->em->getRepository(ApiToken::class)->findOneBy(['tokenHash' => self::hash($accessToken)]);
        if ($token === null || $token->getExpiresAt() < new \DateTimeImmutable()) {
            throw new BadCredentialsException('Token non valido o scaduto');
        }

        return new UserBadge($token->getUser()->getUserIdentifier());
    }

    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }
}
