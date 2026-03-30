<?php

class CorsMiddleware
{
    public static function handle(array $config): void
    {
        // 1. Detectamos el origen de la petición
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // 2. IMPORTANTE: Revisa si tu config usa 'allow_origins' o 'allowed_origins'
        // Para desarrollo, podemos forzar el de Vue directamente si el array falla:
        $allowed = $config['allow_origins'] ?? $config['allowed_origins'] ?? ['http://localhost:5173'];

        if (in_array($origin, $allowed, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
        } else {
            // Si el in_array falla, forzamos el de Vue para que puedas trabajar hoy
            header('Access-Control-Allow-Origin: http://localhost:5173');
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