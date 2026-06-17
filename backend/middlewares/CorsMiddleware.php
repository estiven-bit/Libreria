<?php

class CorsMiddleware
{
    public static function handle(array $config): void
    {
        // 1. Detectamos el origen de la petición
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // Si no hay Origin, intentamos extraerlo del Referer
        if ($origin === '' && !empty($_SERVER['HTTP_REFERER'])) {
            $referer = $_SERVER['HTTP_REFERER'];
            $parts = parse_url($referer);
            if (is_array($parts) && !empty($parts['scheme']) && !empty($parts['host'])) {
                $port = isset($parts['port']) ? ':' . $parts['port'] : '';
                $origin = $parts['scheme'] . '://' . $parts['host'] . $port;
            }
        }

        // Si sigue vacío, usamos el fallback de producción
        if ($origin === '') {
            $origin = 'https://libreria-taupe.vercel.app';
        }

        // 2. Lista de orígenes permitidos desde config + cualquier subdominio *.vercel.app
        $allowed = $config['allow_origins'] ?? $config['allowed_origins'] ?? [];

        $appEnv = strtolower((string)(env('APP_ENV') ?: 'development'));
        $isLocalIp = false;
        if ($appEnv !== 'production' && $appEnv !== 'prod') {
            if (preg_match('#^http://(192\.168\.\d+\.\d+|10\.\d+\.\d+\.\d+|172\.(1[6-9]|2\d|3[0-1])\.\d+\.\d+)(:\d+)?$#', $origin)) {
                $isLocalIp = true;
            }
        }

        $isAllowed = in_array($origin, $allowed, true)
            || (str_ends_with($origin, '.vercel.app') && $origin !== '')
            || $isLocalIp;

        if ($isAllowed) {
            header('Access-Control-Allow-Origin: ' . $origin);
        } else {
            // Si el origen no está listado explícitamente pero es OPTIONS o un fallback de producción,
            // devolvemos el origen de producción en vez del localhost por defecto.
            header('Access-Control-Allow-Origin: https://libreria-taupe.vercel.app');
        }

        header('Access-Control-Allow-Credentials: true');
        // Añadimos X-Requested-With que a veces lo pide Axios/Fetch
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token, X-Requested-With');
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');

        // El navegador siempre envía OPTIONS antes de un GET/POST con headers
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}