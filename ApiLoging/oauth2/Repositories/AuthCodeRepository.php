<?php

namespace LibreriaGabi\OAuth2\Repositories;

use Database;
use League\OAuth2\Server\Entities\AuthCodeEntityInterface;
use League\OAuth2\Server\Repositories\AuthCodeRepositoryInterface;
use LibreriaGabi\OAuth2\Entities\AuthCodeEntity;

class AuthCodeRepository implements AuthCodeRepositoryInterface
{
    public function getNewAuthCode(): AuthCodeEntityInterface
    {
        return new AuthCodeEntity();
    }

    public function persistNewAuthCode(AuthCodeEntityInterface $authCodeEntity): void
    {
        $scopes = array_map(fn($s) => $s->getIdentifier(), $authCodeEntity->getScopes());
        // El IdP nos pasa el session_id en $_SESSION['session_db_ids'][user_id]
        // (puesto por OAuthController::recordSession en login OK). Aquí lo
        // copiamos al auth_code para que viaje hasta el access_token y el
        // BFF pueda detectar la revocación de sesión.
        $userId = (int)$authCodeEntity->getUserIdentifier();
        $sessionId = (string)($_SESSION['session_db_ids'][$userId] ?? '');
        $stmt = Database::connect()->prepare(
            'INSERT INTO oauth_auth_codes (id, user_id, client_id, scopes, session_id, expires_at)
             VALUES (:id, :uid, :cid, :scopes, :sid, :exp)'
        );
        $stmt->execute([
            ':id' => $authCodeEntity->getIdentifier(),
            ':uid' => $userId,
            ':cid' => $authCodeEntity->getClient()->getIdentifier(),
            ':scopes' => implode(',', $scopes),
            ':sid' => $sessionId !== '' ? $sessionId : null,
            ':exp' => $authCodeEntity->getExpiryDateTime()->format('Y-m-d H:i:s'),
        ]);
    }

    public function revokeAuthCode($codeId): void
    {
        $stmt = Database::connect()->prepare(
            'UPDATE oauth_auth_codes SET is_revoked = 1 WHERE id = :id'
        );
        $stmt->execute([':id' => $codeId]);
    }

    public function isAuthCodeRevoked($codeId): bool
    {
        $stmt = Database::connect()->prepare(
            'SELECT is_revoked FROM oauth_auth_codes WHERE id = :id'
        );
        $stmt->execute([':id' => $codeId]);
        $row = $stmt->fetch();
        if (!$row) return true;
        return (bool)$row['is_revoked'];
    }
}
