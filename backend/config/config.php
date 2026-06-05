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

// Helper robusto: lee vars de entorno desde getenv(), $_ENV y $_SERVER (necesario en Vercel PHP)
function env(string $key, string $default = ''): string {
    $v = getenv($key);
    if ($v !== false && $v !== '') return $v;
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
    if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return $_SERVER[$key];
    return $default;
}

// Global configuration with sensible defaults for local development.
return [
    'app' => [
        'name'               => 'Pagina Web Gabi',
        'env'                => env('APP_ENV', 'development'),
        'base_url'           => env('APP_BASE_URL', 'http://localhost'),
        'frontend_url'       => env('FRONTEND_URL', 'http://localhost:5173'),
        'jwt_secret'         => env('JWT_SECRET', 'change_this_secret'),
        'jwt_issuer'         => env('JWT_ISSUER', 'pagina-web-gabi'),
        'jwt_exp_minutes'    => 60,
        'rate_limit_per_minute' => 60,
        'uploads_path'       => __DIR__ . '/../storage/uploads',
        'logs_path'          => __DIR__ . '/../storage/logs/app.log',
        'public_url'         => rtrim(env('APP_PUBLIC_URL', 'http://localhost/libreria_gabi/backend/public'), '/'),
        'allow_origins'      => array_filter([
            'http://localhost:5173',
            env('FRONTEND_URL', ''),
        ]),
    ],
    'db' => [
        'host' => env('DB_HOST', '127.0.0.1'),
        'port' => env('DB_PORT', '3306'),
        'name' => env('DB_NAME', 'libreria_gabi'),
        'user' => env('DB_USER', 'root'),
        'pass' => env('DB_PASS', ''),
        'charset' => 'utf8mb4',
    ],
    'mail' => [
        'host' => env('MAIL_HOST', 'smtp.gmail.com'),
        'port' => (int)env('MAIL_PORT', '587'),
        'username' => env('MAIL_USERNAME', ''),
        'password' => env('MAIL_PASSWORD', ''),
        'encryption' => env('MAIL_ENCRYPTION', 'tls'),
        'from_email' => env('MAIL_FROM', 'no-reply@libreria-gabi.com'),
        'from_name' => env('MAIL_FROM_NAME', 'Libreria Gabi'),
    ],
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),
        'chat_id' => env('TELEGRAM_CHAT_ID', ''),
    ],
    'stripe' => [
        'secret_key' => env('STRIPE_SECRET_KEY', ''),
        'currency' => env('STRIPE_CURRENCY', 'usd'),
    ],
];
