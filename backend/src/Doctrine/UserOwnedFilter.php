<?php

namespace App\Doctrine;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query\Filter\SQLFilter;

/**
 * Filtro unico di isolamento: ogni entita' con associazione "user" viene
 * limitata alle righe dell'utente autenticato. Non si abilita nel worker.
 */
final class UserOwnedFilter extends SQLFilter
{
    public function addFilterConstraint(ClassMetadata $targetEntity, string $targetTableAlias): string
    {
        if (!$targetEntity->hasAssociation('user')) {
            return '';
        }

        return sprintf('%s.user_id = %s', $targetTableAlias, $this->getParameter('user_id'));
    }
}
