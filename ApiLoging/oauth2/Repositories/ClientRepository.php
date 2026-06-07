<?php

namespace LibreriaGabi\OAuth2\Repositories;

use Database;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use LibreriaGabi\OAuth2\Entities\ClientEntity;

class ClientRepository implements ClientRepositoryInterface
{
    public function getClientEntity($clientIdentifier): ?ClientEntityInterface
    {
        $stmt = Database::connect()->prepare(
            'SELECT id, name, secret, redirect_uri, is_confidential
               FROM oauth_clients
              WHERE id = :id AND is_revoked = 0'
        );
        $stmt->execute([':id' => $clientIdentifier]);
        $row = $stmt->fetch();
        if (!$row) return null;

        $client = new ClientEntity();
        $client->setIdentifier($row['id']);
        $client->setName($row['name']);
        $client->setRedirectUri($row['redirect_uri']);
        $client->setConfidential((bool)$row['is_confidential']);
        return $client;
    }

    public function validateClient($clientIdentifier, $clientSecret, $grantType): bool
    {
        $stmt = Database::connect()->prepare(
            'SELECT secret, is_confidential, allowed_grants
               FROM oauth_clients
              WHERE id = :id AND is_revoked = 0'
        );
        $stmt->execute([':id' => $clientIdentifier]);
        $row = $stmt->fetch();
        if (!$row) return false;

        $allowed = array_map('trim', explode(',', $row['allowed_grants']));
        if ($grantType !== null && !in_array($grantType, $allowed, true)) return false;

        if (!$row['is_confidential']) return true;

        return is_string($clientSecret) && hash_equals((string)$row['secret'], $clientSecret);
    }
}
