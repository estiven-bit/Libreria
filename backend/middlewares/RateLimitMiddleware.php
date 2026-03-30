<?php

require_once __DIR__ . '/../utils/RateLimiter.php';

class RateLimitMiddleware
{
    public static function handle(array $config): void
    {
        $key = $_SERVER['REMOTE_ADDR'] . ':' . ($_SERVER['REQUEST_URI'] ?? '');
        $allowed = RateLimiter::check($key, (int)$config['rate_limit_per_minute'], 60);
        if (!$allowed) {
            Response::json(['error' => 'Too many requests'], 429);
        }
    }
}
