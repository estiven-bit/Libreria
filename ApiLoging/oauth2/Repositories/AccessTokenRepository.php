<?php

namespace LibreriaGabi\OAuth2\Repositories;

use Database;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Repositories\AccessTokenRepositoryInterface;
use LibreriaGabi\OAuth2\Entities\AccessTokenEntity;

class AccessTokenRepository implements AccessTokenRepositoryInterface
{
    public function getNewToken(
        ClientEntityInterface $clientEntity,
        array $scopes,
        $userIdentifier = null
    ): AccessTokenEntityInterface {
        $token = new AccessTokenEntity();
        $token->setClient($clientEntity);
        foreach ($scopes as $scope) $token->addScope($scope);
        if ($userIdentifier !== null) $token->setUserIdentifier($userIdentifier);
        return $token;
    }

    public function persistNewAccessToken(AccessTokenEntityInterface $accessTokenEntity): void
    {
        $scopes = array_map(fn($s) => $s->getIdentifier(), $accessTokenEntity->getScopes());
        $userId = (int)$accessTokenEntity->getUserIdentifier();

        // Copiamos el session_id del auth_code más reciente del usuario.
        // OJO: league 8.x revoca el auth_code DESPUÉS de persistir el access_token
        // (no antes), así que NO podemos filtrar por is_revoked=1 aquí — el
        // código en uso aún está revoked=0 cuando llegamos a este punto. Filtramos
        // por session_id IS NOT NULL y expires_at DESC para coger el más reciente.
        // Esto enlaza el access_token a la sesión multi-device, así que
        // isAccessTokenRevoked puede detectar revocaciones del user_sessions.
        $db = Database::connect();
        $codeStmt = $db->prepare(
            'SELECT session_id FROM oauth_auth_codes
             WHERE user_id = :uid AND session_id IS NOT NULL
             ORDER BY expires_at DESC LIMIT 1'
        );
        $codeStmt->execute([':uid' => $userId]);
        $codeRow = $codeStmt->fetch();
        $sessionId = $codeRow ? (string)$codeRow['session_id'] : null;

        $stmt = $db->prepare(
            'INSERT INTO oauth_access_tokens (id, user_id, client_id, scopes, session_id, expires_at)
             VALUES (:id, :uid, :cid, :scopes, :sid, :exp)'
        );
        $stmt->execute([
            ':id' => $accessTokenEntity->getIdentifier(),
            ':uid' => $userId,
            ':cid' => $accessTokenEntity->getClient()->getIdentifier(),
            ':scopes' => implode(',', $scopes),
            ':sid' => $sessionId,
            ':exp' => $accessTokenEntity->getExpiryDateTime()->format('Y-m-d H:i:s'),
        ]);
    }

    public function revokeAccessToken($tokenId): void
    {
        $stmt = Database::connect()->prepare(
            'UPDATE oauth_access_tokens SET is_revoked = 1 WHERE id = :id'
        );
        $stmt->execute([':id' => $tokenId]);
    }

    public function isAccessTokenRevoked($tokenId): bool
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'SELECT is_revoked, session_id FROM oauth_access_tokens WHERE id = :id'
        );
        $stmt->execute([':id' => $tokenId]);
        $row = $stmt->fetch();
        if (!$row) return true;
        if ((bool)$row['is_revoked']) return true;

        // Si el token está enlazado a una user_session multi-device, también
        // está revocado si esa sesión fue cerrada desde "Sesiones" del settings.
        if (!empty($row['session_id'])) {
            $sessCheck = $db->prepare(
                'SELECT 1 FROM user_sessions
                 WHERE id = :sid AND revoked_at IS NULL LIMIT 1'
            );
            $sessCheck->execute([':sid' => $row['session_id']]);
            if (!$sessCheck->fetch()) return true; // session revocada
        }

        return false;
    }
}
