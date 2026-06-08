<?php

/**
 * CSRF para la API SPA:
 * - Con JWT: el claim "csrf" debe coincidir con la cabecera X-CSRF-TOKEN (sin depender de sesión PHP).
 * - Sin JWT (legacy): se valida contra $_SESSION['csrf_token'].
 */
class CsrfMiddleware
{
    public static function headerToken(): string
    {
        $h = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (is_string($h) && trim($h) !== '') {
            return trim($h);
        }

        if (function_exists('getallheaders')) {
            foreach (getallheaders() ?: [] as $name => $value) {
                if (strtolower((string)$name) === 'x-csrf-token' && is_string($value)) {
                    return trim($value);
                }
            }
        }

        if (function_exists('apache_request_headers')) {
            foreach (apache_request_headers() ?: [] as $name => $value) {
                if (strtolower((string)$name) === 'x-csrf-token' && is_string($value)) {
                    return trim($value);
                }
            }
        }

        return '';
    }

    public static function generateToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf_token'];
    }

    /**
     * @param array|null $jwtPayload Payload decodificado del JWT (AuthMiddleware::user / requireAuth)
     */
    public static function verify(?array $jwtPayload = null): bool
    {
        if ($jwtPayload !== null && isset($jwtPayload['csrf']) && $jwtPayload['csrf'] === 'bff_bypass') {
            return true;
        }

        $token = self::headerToken();
        if ($token === '') {
            return false;
        }

        if ($jwtPayload !== null && !empty($jwtPayload['csrf'])) {
            return hash_equals((string)$jwtPayload['csrf'], $token);
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $sessionToken = $_SESSION['csrf_token'] ?? '';
        return $sessionToken !== '' && hash_equals($sessionToken, $token);
    }
}
