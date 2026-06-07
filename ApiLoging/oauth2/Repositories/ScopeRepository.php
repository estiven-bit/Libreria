<?php

namespace LibreriaGabi\OAuth2\Repositories;

use Database;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Repositories\ScopeRepositoryInterface;
use LibreriaGabi\OAuth2\Entities\ScopeEntity;

class ScopeRepository implements ScopeRepositoryInterface
{
    public function getScopeEntityByIdentifier($identifier): ?ScopeEntityInterface
    {
        $stmt = Database::connect()->prepare('SELECT id FROM oauth_scopes WHERE id = :id');
        $stmt->execute([':id' => $identifier]);
        if (!$stmt->fetch()) return null;
        $scope = new ScopeEntity();
        $scope->setIdentifier($identifier);
        return $scope;
    }

    public function finalizeScopes(
        array $scopes,
        $grantType,
        ClientEntityInterface $clientEntity,
        $userIdentifier = null,
        ?string $authCodeId = null
    ): array {
        // Sin filtros adicionales: el cliente recibe los scopes que pidió y existen.
        return $scopes;
    }
}
