<?php

namespace LibreriaGabi\OAuth2\Repositories;

use Database;
use League\OAuth2\Server\Entities\RefreshTokenEntityInterface;
use League\OAuth2\Server\Repositories\RefreshTokenRepositoryInterface;
use LibreriaGabi\OAuth2\Entities\RefreshTokenEntity;

class RefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    public function getNewRefreshToken(): ?RefreshTokenEntityInterface
    {
        return new RefreshTokenEntity();
    }

    public function persistNewRefreshToken(RefreshTokenEntityInterface $refreshTokenEntity): void
    {
        $stmt = Database::connect()->prepare(
            'INSERT INTO oauth_refresh_tokens (id, access_token_id, expires_at)
             VALUES (:id, :atid, :exp)'
        );
        $stmt->execute([
            ':id' => $refreshTokenEntity->getIdentifier(),
            ':atid' => $refreshTokenEntity->getAccessToken()->getIdentifier(),
            ':exp' => $refreshTokenEntity->getExpiryDateTime()->format('Y-m-d H:i:s'),
        ]);
    }

    public function revokeRefreshToken($tokenId): void
    {
        $stmt = Database::connect()->prepare(
            'UPDATE oauth_refresh_tokens SET is_revoked = 1 WHERE id = :id'
        );
        $stmt->execute([':id' => $tokenId]);
    }

    public function isRefreshTokenRevoked($tokenId): bool
    {
        $stmt = Database::connect()->prepare(
            'SELECT is_revoked FROM oauth_refresh_tokens WHERE id = :id'
        );
        $stmt->execute([':id' => $tokenId]);
        $row = $stmt->fetch();
        if (!$row) return true;
        return (bool)$row['is_revoked'];
    }
}
