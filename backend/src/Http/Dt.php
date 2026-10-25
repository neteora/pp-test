<?php

namespace App\Http;

final class Dt
{
    public static function iso(?\DateTimeInterface $d): ?string
    {
        return $d === null ? null : \DateTimeImmutable::createFromInterface($d)
            ->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z');
    }

    /**
     * Converte l'input in UTC. Se manca l'offset, l'orario e' interpretato nel fuso dell'utente.
     */
    public static function parseToUtc(string $value, string $userTimezone): \DateTimeImmutable
    {
        $utc = new \DateTimeZone('UTC');
        try {
            $hasOffset = (bool) preg_match('/(Z|[+-]\d{2}:?\d{2})$/i', $value);
            $tz = $hasOffset ? $utc : new \DateTimeZone($userTimezone);

            return (new \DateTimeImmutable($value, $tz))->setTimezone($utc);
        } catch (\Exception) {
            throw new \InvalidArgumentException('Data non valida');
        }
    }
}
