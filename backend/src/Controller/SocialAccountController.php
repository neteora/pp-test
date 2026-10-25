<?php

namespace App\Controller;

use App\Entity\SocialAccount;
use App\Enum\Platform;
use App\Http\Dt;
use App\Social\PublisherRegistry;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

/** Collegamento manuale: l'utente incolla id account e token (nessun OAuth). Un account per piattaforma. */
final class SocialAccountController extends ApiController
{
    public function __construct(private EntityManagerInterface $em, private PublisherRegistry $publishers) {}

    #[Route('/api/social-accounts', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $accounts = $this->em->getRepository(SocialAccount::class)->findBy([], ['id' => 'ASC']);

        return new JsonResponse([
            'items' => array_map(static fn (SocialAccount $a) => $a->toArray(), $accounts),
            'supportedPlatforms' => $this->publishers->platforms(),
        ]);
    }

    #[Route('/api/social-accounts/{platform}', methods: ['PUT'])]
    public function upsert(string $platform, Request $request): JsonResponse
    {
        $p = Platform::tryFrom($platform) ?? throw new BadRequestHttpException('Piattaforma sconosciuta');
        if (!$this->publishers->supports($p)) {
            throw new BadRequestHttpException('Piattaforma non ancora supportata');
        }
        $b = $this->body($request);
        $externalId = trim($this->requireString($b, 'externalAccountId', 255));
        $token = trim($this->requireString($b, 'accessToken', 4000));
        $expires = isset($b['tokenExpiresAt']) && $b['tokenExpiresAt'] !== ''
            ? $this->parseDate((string) $b['tokenExpiresAt'])
            : null;

        $account = $this->em->getRepository(SocialAccount::class)->findOneBy(['platform' => $p]);
        if ($account === null) {
            $account = new SocialAccount($this->me(), $p, $externalId, $token);
            $account->replaceToken($externalId, $token, $expires);
            $this->em->persist($account);
        } else {
            $account->replaceToken($externalId, $token, $expires);
        }
        $this->em->flush();

        return new JsonResponse($account->toArray());
    }

    private function parseDate(string $v): \DateTimeImmutable
    {
        try {
            return Dt::parseToUtc($v, $this->me()->getTimezone());
        } catch (\InvalidArgumentException $e) {
            throw new BadRequestHttpException($e->getMessage());
        }
    }
}
