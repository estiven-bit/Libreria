<?php

namespace LibreriaGabi\OAuth2\Repositories;

use Database;
use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Entities\UserEntityInterface;
use League\OAuth2\Server\Repositories\UserRepositoryInterface;
use LibreriaGabi\OAuth2\Entities\UserEntity;

/**
 * UserRepository del IdP. La interfaz de league/oauth2-server (PasswordGrant)
 * solo se usaría con grant_type=password — no lo habilitamos. Pero exponemos
 * findById() como helper para BffController/OAuthController::userinfo.
 *
 * El "usuario" del IdP vive en la tabla `users` ya existente. Aquí solo lo
 * resolvemos a un objeto plano con `claims` para la respuesta OIDC.
 */
class UserRepository implements UserRepositoryInterface
{
    public function getUserEntityByUserCredentials(
        $username,
        $password,
        $grantType,
        ClientEntityInterface $clientEntity
    ): ?UserEntityInterface {
        // Password grant deshabilitado — el flujo es Authorization Code + IdP login form.
        return null;
    }

    /**
     * Devuelve un objeto con la propiedad `claims` lista para JSON en /userinfo.
     * Si el usuario no existe, devuelve null.
     */
    public static function findById(int $userId): ?object
    {
        $stmt = Database::connect()->prepare(
            'SELECT id, username, email, is_email_verified, name, first_name, last_name, role
               FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();
        if (!$row) return null;

        $claims = [
            'sub' => (string)$row['id'],
            'email' => $row['email'],
            'email_verified' => (bool)$row['is_email_verified'],
            'name' => $row['name'],
            'given_name' => $row['first_name'],
            'family_name' => $row['last_name'],
            'preferred_username' => $row['username'],
            'role' => $row['role'],
        ];

        return (object)['id' => (int)$row['id'], 'claims' => $claims];
    }
}
