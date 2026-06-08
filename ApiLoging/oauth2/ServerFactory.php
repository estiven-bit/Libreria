<?php

namespace LibreriaGabi\OAuth2;

use DateInterval;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\ResourceServer;
use LibreriaGabi\OAuth2\Repositories\AccessTokenRepository;
use LibreriaGabi\OAuth2\Repositories\AuthCodeRepository;
use LibreriaGabi\OAuth2\Repositories\ClientRepository;
use LibreriaGabi\OAuth2\Repositories\RefreshTokenRepository;
use LibreriaGabi\OAuth2\Repositories\ScopeRepository;

/**
 * Construye AuthorizationServer y ResourceServer del IdP.
 *
 * Las claves y el issuer se leen del entorno; los repositorios son
 * implementaciones propias contra la BBDD `libreriagabi_users`.
 */
class ServerFactory
{
    private static function basePath(): string
    {
        return dirname(__DIR__);
    }

    public static function issuer(): string
    {
        return env('OIDC_ISSUER') ?: 'http://localhost:8000';
    }

    public static function privateKeyPath(): string
    {
        return self::basePath() . '/storage/oauth-keys/private.key';
    }

    public static function publicKeyPath(): string
    {
        return self::basePath() . '/storage/oauth-keys/public.key';
    }

    public static function encryptionKey(): string
    {
        $path = self::basePath() . '/storage/oauth-keys/encryption.key';
        return trim((string)file_get_contents($path));
    }

    public static function authorizationServer(): AuthorizationServer
    {
        $server = new AuthorizationServer(
            new ClientRepository(),
            new AccessTokenRepository(),
            new ScopeRepository(),
            new \League\OAuth2\Server\CryptKey(self::privateKeyPath(), null, false),
            self::encryptionKey(),
            new OidcBearerTokenResponse(self::issuer(), self::privateKeyPath())
        );

        // Authorization Code Grant con PKCE obligatorio para clientes públicos.
        $authCodeGrant = new AuthCodeGrant(
            new AuthCodeRepository(),
            new RefreshTokenRepository(),
            new DateInterval('PT10M') // codes expiran en 10 minutos
        );
        $authCodeGrant->setRefreshTokenTTL(new DateInterval('P1M')); // refresh 1 mes
        $server->enableGrantType($authCodeGrant, new DateInterval('PT1H')); // access 1 hora

        // Refresh Token Grant
        $refreshGrant = new RefreshTokenGrant(new RefreshTokenRepository());
        $refreshGrant->setRefreshTokenTTL(new DateInterval('P1M'));
        $server->enableGrantType($refreshGrant, new DateInterval('PT1H'));

        return $server;
    }

    public static function resourceServer(): ResourceServer
    {
        return new ResourceServer(
            new AccessTokenRepository(),
            new \League\OAuth2\Server\CryptKey(self::publicKeyPath(), null, false)
        );
    }
}
