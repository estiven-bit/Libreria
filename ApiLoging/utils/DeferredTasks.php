<?php

/**
 * Ejecuta trabajo pesado (p.ej. SMTP) después de enviar la respuesta HTTP
 * al navegador, para no bloquear redirects de login.
 */
class DeferredTasks
{
    /** @var callable[] */
    private static array $callbacks = [];

    public static function runAfterResponse(callable $callback): void
    {
        self::$callbacks[] = $callback;
    }

    public static function flush(): void
    {
        if (self::$callbacks === []) {
            return;
        }

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        } else {
            if (ob_get_level() > 0) {
                @ob_end_flush();
            }
            @flush();
        }

        foreach (self::$callbacks as $callback) {
            try {
                $callback();
            } catch (\Throwable $e) {
                error_log('[DeferredTasks] ' . $e->getMessage());
            }
        }
        self::$callbacks = [];
    }

    public static function redirect(string $url): void
    {
        header('Location: ' . $url);
        self::flush();
        exit;
    }
}
