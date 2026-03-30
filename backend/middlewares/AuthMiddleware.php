<?php

require_once __DIR__ . '/../utils/Jwt.php';

class AuthMiddleware
{
    public static function user(array $config): ?array
    {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (!$header || !str_starts_with($header, 'Bearer ')) {
            return null;
        }

        $token = trim(substr($header, 7));
        return Jwt::decode($token, $config['jwt_secret']);
    }

    public static function requireAuth(array $config): array
    {
        $user = self::user($config);
        if (!$user) {
            Response::json(['error' => 'Unauthorized'], 401);
        }
        return $user;
    }
}
