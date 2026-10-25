<?php

namespace App\Service;

use App\Enum\PostStatus;
use Doctrine\DBAL\Connection;

/**
 * Transizioni di stato atomiche (compare-and-set con UPDATE ... WHERE status = :from):
 * la riga e' bloccata dall'UPDATE, quindi due attori concorrenti non possono vincere entrambi.
 */
class PostTransitions
{
    private const SETTABLE = ['last_error', 'external_id', 'container_id', 'attempts'];

    public function __construct(private Connection $db) {}

    /**
     * @param array<string, scalar|null> $set colonne extra (solo quelle consentite)
     * @return bool true se questo chiamante ha effettuato la transizione
     */
    public function transition(
        int $id,
        PostStatus $from,
        PostStatus $to,
        ?int $userId = null,
        ?\DateTimeImmutable $dueBy = null,
        array $set = [],
    ): bool {
        $sql = 'UPDATE post_targets SET status = :to, updated_at = :now';
        $params = ['to' => $to->value, 'now' => self::fmt(new \DateTimeImmutable()), 'id' => $id, 'from' => $from->value];

        foreach ($set as $col => $value) {
            if (!in_array($col, self::SETTABLE, true)) {
                throw new \InvalidArgumentException('Colonna non consentita');
            }
            $sql .= sprintf(', %s = :set_%s', $col, $col);
            $params['set_'.$col] = $value;
        }

        $sql .= ' WHERE id = :id AND status = :from';
        if ($userId !== null) {
            $sql .= ' AND user_id = :uid';
            $params['uid'] = $userId;
        }
        if ($dueBy !== null) {
            $sql .= ' AND scheduled_at <= :due';
            $params['due'] = self::fmt($dueBy);
        }

        return $this->db->executeStatement($sql, $params) === 1;
    }

    public function markFailed(int $id, string $error): void
    {
        $this->db->executeStatement(
            "UPDATE post_targets SET status = 'failed', last_error = :e, updated_at = :now
             WHERE id = :id AND status IN ('queued', 'publishing')",
            ['e' => mb_substr($error, 0, 500), 'now' => self::fmt(new \DateTimeImmutable()), 'id' => $id],
        );
    }

    /** @return array<int, array{0:int,1:string}> coppie [postId, platform] */
    public function findDue(\DateTimeImmutable $now, int $limit): array
    {
        $rows = $this->db->fetchAllAssociative(
            "SELECT p.id, a.platform FROM post_targets p
             JOIN social_accounts a ON a.id = p.social_account_id
             WHERE p.status = 'pending' AND p.scheduled_at <= :now AND a.status = 'active'
             ORDER BY p.scheduled_at ASC
             LIMIT {$limit}",
            ['now' => self::fmt($now)],
        );

        return array_map(static fn (array $r) => [(int) $r['id'], (string) $r['platform']], $rows);
    }

    public static function fmt(\DateTimeImmutable $d): string
    {
        return $d->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
