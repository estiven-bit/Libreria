<?php
// Global configuration with sensible defaults for local development.
return [
    'app' => [
        'name' => 'Pagina Web Gabi',
        'env' => getenv('APP_ENV') ?: 'development',
        'base_url' => getenv('APP_BASE_URL') ?: 'http://localhost',
        'frontend_url' => getenv('FRONTEND_URL') ?: 'http://localhost:5173',
        'jwt_secret' => getenv('JWT_SECRET') ?: 'change_this_secret',
        'jwt_issuer' => getenv('JWT_ISSUER') ?: 'pagina-web-gabi',
        'jwt_exp_minutes' => 60,
        'rate_limit_per_minute' => 60,
        'uploads_path' => __DIR__ . '/../storage/uploads',
        'logs_path' => __DIR__ . '/../storage/logs/app.log',
        'allow_origins' => [
            getenv('FRONTEND_URL') ?: 'http://localhost:5173'
        ],
    ],
    'db' => [
        'host' => getenv('DB_HOST') ?: '127.0.0.1',
        'port' => getenv('DB_PORT') ?: '3306',
        'name' => getenv('DB_NAME') ?: 'libreria_gabi',
        'user' => getenv('DB_USER') ?: 'root',
        'pass' => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],
    'mail' => [
        'from_email' => getenv('MAIL_FROM') ?: 'no-reply@libreria-gabi.com',
        'from_name' => getenv('MAIL_FROM_NAME') ?: 'Libreria Gabi',
    ],
];
