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
        // 1. Comprobar si la petición viene del proxy BFF
        if (isset($_SERVER['HTTP_X_LIBRERIAGABI_SIGNATURE'])) {
            $bffUser = $_SERVER['HTTP_X_LIBRERIAGABI_USER'] ?? '';
            $bffRole = $_SERVER['HTTP_X_LIBRERIAGABI_ROLE'] ?? 'user';
            $bffEmail = $_SERVER['HTTP_X_LIBRERIAGABI_EMAIL'] ?? '';
            $timestamp = (int)($_SERVER['HTTP_X_LIBRERIAGABI_TIMESTAMP'] ?? 0);
            $receivedSignature = $_SERVER['HTTP_X_LIBRERIAGABI_SIGNATURE'] ?? '';

            // Protección de replay (margen de 5 minutos)
            if (abs(time() - $timestamp) < 300) {
                $body = file_get_contents('php://input');
                $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
                $isMultipart = stripos($contentType, 'multipart/form-data') === 0;
                $bodyForHash = $isMultipart ? '' : (string)$body;
                $bodyHash = hash('sha256', $bodyForHash);

                $canonical = $timestamp . "\n" . $bffUser . "\n" . $bffRole . "\n" . $bffEmail . "\n" . $bodyHash;
                $serviceSecret = $config['bff_service_secret'] ?? '24703063d1516c84623d5e2013e758c51a8f8a815f40e0fc588f9d744c887dab';
                $computedSignature = hash_hmac('sha256', $canonical, $serviceSecret);

                if (hash_equals($computedSignature, $receivedSignature)) {
                    // Mapeamos roles del IdP al formato esperado por el backend
                    $mappedRole = strtolower($bffRole) === 'admin' ? 'ADMINISTRADOR' : 'USUARIO';
                    return [
                        'sub' => (int)$bffUser,
                        'email' => $bffEmail,
                        'role' => $mappedRole,
                        'csrf' => 'bff_bypass' // Bypass de CSRF porque el BFF ya lo gestiona
                    ];
                }
            }
        }

        // 2. Fallback al flujo normal JWT
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
