<?php

(function (): void {
    $envFile = __DIR__ . '/../.env';
    if (!is_readable($envFile)) {
        return;
    }
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if ($key === '') {
            continue;
        }
        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
    }
})();

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
        /** URL base de /public (imágenes subidas accesibles por el navegador) */
        'public_url' => rtrim(getenv('APP_PUBLIC_URL') ?: 'http://localhost/libreria_gabi/backend/public', '/'),
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
        'host' => getenv('MAIL_HOST') ?: 'smtp.gmail.com',
        'port' => (int)(getenv('MAIL_PORT') ?: 587),
        'username' => getenv('MAIL_USERNAME') ?: '',
        'password' => getenv('MAIL_PASSWORD') ?: '',
        'encryption' => getenv('MAIL_ENCRYPTION') ?: 'tls',
        'from_email' => getenv('MAIL_FROM') ?: 'no-reply@libreria-gabi.com',
        'from_name' => getenv('MAIL_FROM_NAME') ?: 'Libreria Gabi',
    ],
    'telegram' => [
        'bot_token' => getenv('TELEGRAM_BOT_TOKEN') ?: '',
        'chat_id' => getenv('TELEGRAM_CHAT_ID') ?: '',
    ],
    'stripe' => [
        'secret_key' => getenv('STRIPE_SECRET_KEY') ?: '',
        'currency' => getenv('STRIPE_CURRENCY') ?: 'usd',
    ],
];
