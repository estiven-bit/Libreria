<?php

class CorsMiddleware
{
    public static function handle(array $config): void
    {
        // 1. Detectamos el origen de la petición
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // 2. Lista de orígenes permitidos desde config + cualquier subdominio *.vercel.app
        $allowed = $config['allow_origins'] ?? $config['allowed_origins'] ?? [];

        $isAllowed = in_array($origin, $allowed, true)
            || (str_ends_with($origin, '.vercel.app') && $origin !== '');
        if ($isAllowed) {
            header('Access-Control-Allow-Origin: ' . $origin);
        } elseif (!empty($allowed)) {
            // Solo si hay origenes configurados, devolvemos el primero
            header('Access-Control-Allow-Origin: ' . $allowed[0]);
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