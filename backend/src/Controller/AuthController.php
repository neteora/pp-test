<?php

namespace App\Controller;

use App\Entity\ApiToken;
use App\Entity\User;
use App\Http\Dt;
use App\Security\ApiTokenHandler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class AuthController extends ApiController
{
    private const TOKEN_TTL = '+7 days';

    public function __construct(
        private EntityManagerInterface $em,
        private UserPasswordHasherInterface $hasher,
    ) {}

    #[Route('/api/auth/register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $b = $this->body($request);
        $email = mb_strtolower(trim($this->requireString($b, 'email', 180)));
        $password = $this->requireString($b, 'password', 200);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new BadRequestHttpException('Email non valida');
        }
        if (mb_strlen($password) < 8) {
            throw new BadRequestHttpException('La password deve avere almeno 8 caratteri');
        }
        if ($this->em->getRepository(User::class)->findOneBy(['email' => $email]) !== null) {
            throw new ConflictHttpException('Email gia\' registrata');
        }

        $user = new User($email);
        $user->setPassword($this->hasher->hashPassword($user, $password));
        if (isset($b['timezone'])) {
            $user->setTimezone($this->validTimezone($b['timezone']));
        }
        $this->em->persist($user);
        $this->em->flush();

        return new JsonResponse($this->issueToken($user), Response::HTTP_CREATED);
    }

    #[Route('/api/auth/login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $b = $this->body($request);
        $email = mb_strtolower(trim((string) ($b['email'] ?? '')));
        $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($user === null || !$this->hasher->isPasswordValid($user, (string) ($b['password'] ?? ''))) {
            throw new UnauthorizedHttpException('', 'Credenziali non valide');
        }

        return new JsonResponse($this->issueToken($user));
    }

    #[Route('/api/auth/logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $header = (string) $request->headers->get('Authorization', '');
        $plain = preg_match('/^Bearer\s+(.+)$/i', $header, $m) ? $m[1] : '';
        $token = $this->em->getRepository(ApiToken::class)->findOneBy(['tokenHash' => ApiTokenHandler::hash($plain)]);
        if ($token !== null) {
            $this->em->remove($token);
            $this->em->flush();
        }

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/api/me', methods: ['GET'])]
    public function profile(): JsonResponse
    {
        return new JsonResponse($this->me()->toArray());
    }

    #[Route('/api/me', methods: ['PATCH'])]
    public function updateMe(Request $request): JsonResponse
    {
        $b = $this->body($request);
        $user = $this->me();
        if (isset($b['timezone'])) {
            $user->setTimezone($this->validTimezone($b['timezone']));
        }
        $this->em->flush();

        return new JsonResponse($user->toArray());
    }

    private function validTimezone(mixed $tz): string
    {
        if (!is_string($tz) || !in_array($tz, \DateTimeZone::listIdentifiers(), true)) {
            throw new BadRequestHttpException('Fuso orario non valido');
        }

        return $tz;
    }

    /** @return array{token:string, expiresAt:?string, user:array} */
    private function issueToken(User $user): array
    {
        $plain = bin2hex(random_bytes(32));
        $expires = new \DateTimeImmutable(self::TOKEN_TTL);
        $this->em->persist(new ApiToken($user, ApiTokenHandler::hash($plain), $expires));
        $this->em->flush();

        return ['token' => $plain, 'expiresAt' => Dt::iso($expires), 'user' => $user->toArray()];
    }
}
