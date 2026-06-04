<?php

require_once __DIR__ . '/../utils/Jwt.php';

use App\Utils\Jwt;

class AuthMiddleware
{
    /**
     * Apache/CGI a veces no rellena HTTP_AUTHORIZATION; probamos varias fuentes.
     */
    private static function authorizationHeader(): string
    {
        $candidates = [
            $_SERVER['HTTP_AUTHORIZATION'] ?? '',
            $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '',
        ];

        foreach ($candidates as $h) {
            $h = is_string($h) ? trim($h) : '';
            if ($h !== '') {
                return $h;
            }
        }

        if (function_exists('getallheaders')) {
            foreach (getallheaders() ?: [] as $name => $value) {
                if (strtolower((string)$name) === 'authorization' && is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        if (function_exists('apache_request_headers')) {
            foreach (apache_request_headers() ?: [] as $name => $value) {
                if (strtolower((string)$name) === 'authorization' && is_string($value) && $value !== '') {
                    return $value;
                }
            }
        }

        return '';
    }

    public static function user(array $config): ?array
    {
        $header = self::authorizationHeader();
        if ($header === '' || !preg_match('/^Bearer\s+(.+)$/i', $header, $m)) {
            return null;
        }

        $token = trim($m[1]);
        if ($token === '') {
            return null;
        }

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
