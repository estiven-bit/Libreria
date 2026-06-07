<?php

namespace LibreriaGabi\OAuth2;

use Firebase\JWT\JWT;
use League\OAuth2\Server\Entities\AccessTokenEntityInterface;
use League\OAuth2\Server\ResponseTypes\BearerTokenResponse;
use LibreriaGabi\OAuth2\Repositories\UserRepository;

/**
 * Extiende BearerTokenResponse para añadir un id_token firmado con RS256
 * cuando el scope incluye `openid`. Sin esto, league/oauth2-server emite
 * solo access_token + refresh_token y el cliente OIDC no recibe la identidad.
 */
class OidcBearerTokenResponse extends BearerTokenResponse
{
    private string $issuer;
    private string $privateKeyPath;

    public function __construct(string $issuer, string $privateKeyPath)
    {
        $this->issuer = $issuer;
        $this->privateKeyPath = $privateKeyPath;
    }

    protected function getExtraParams(AccessTokenEntityInterface $accessToken): array
    {
        $scopes = array_map(fn($s) => $s->getIdentifier(), $accessToken->getScopes());
        if (!in_array('openid', $scopes, true)) {
            return [];
        }

        $userId = (int)$accessToken->getUserIdentifier();
        $user = UserRepository::findById($userId);
        if (!$user) return [];

        $now = time();
        $payload = array_merge($user->claims, [
            'iss' => $this->issuer,
            'aud' => $accessToken->getClient()->getIdentifier(),
            'iat' => $now,
            'exp' => $now + 3600,
            'auth_time' => $now,
        ]);

        $privateKey = file_get_contents($this->privateKeyPath);
        $idToken = JWT::encode($payload, $privateKey, 'RS256', 'oauth2-server-key');

        return ['id_token' => $idToken];
    }
}
