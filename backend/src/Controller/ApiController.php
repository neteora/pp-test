<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

abstract class ApiController extends AbstractController
{
    protected function me(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }

    /** @return array<string, mixed> */
    protected function body(Request $request): array
    {
        try {
            return $request->toArray();
        } catch (\Throwable) {
            throw new BadRequestHttpException('JSON non valido');
        }
    }

    protected function requireString(array $body, string $key, int $max = 10000): string
    {
        $v = $body[$key] ?? null;
        if (!is_string($v) || trim($v) === '' || mb_strlen($v) > $max) {
            throw new BadRequestHttpException("Campo '$key' mancante o non valido");
        }

        return $v;
    }
}
