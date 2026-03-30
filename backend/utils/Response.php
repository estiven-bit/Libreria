<?php
// backend/utils/Response.php

class Response
{
    public static function json($data, int $status = 200): void
    {
        // 1. FORZAR CORS AQUÍ (Esto es lo que falta)
        header("Access-Control-Allow-Origin: http://localhost:5173");
        header("Access-Control-Allow-Credentials: true");
        header("Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS");
        header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-CSRF-Token");

        // 2. Configuración normal de la respuesta
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        
        // 3. Enviar datos y terminar
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }
}