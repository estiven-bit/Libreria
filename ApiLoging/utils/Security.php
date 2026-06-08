<?php

class Security
{
    public static function bootstrapCors(): void
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;

        if (is_string($origin) && $origin !== '') {
            // Same-origin: el form de /idp/register, /idp/settings, etc. vive
            // en el propio dominio del IdP. Los browsers añaden Origin a todos
            // los POST, incluso same-origin, así que sin este shortcut el
            // form de registro recibe 403 origin_not_allowed (porque
            // auth.libreriagabi.com no está en CORS_ALLOWED_ORIGINS — la
            // allowlist es para clientes externos del SSO).
            $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
                ? 'https' : 'http';
            $selfOrigin = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? '');
            if ($origin === $selfOrigin) {
                // Same-origin: nada que validar ni cabeceras CORS que añadir.
                header('Access-Control-Allow-Headers: Content-Type, Authorization');
                header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
                return;
            }

            $allowedOrigins = self::allowedOrigins();
            if (!in_array($origin, $allowedOrigins, true)) {
                Response::json(['error' => 'origin not allowed'], 403);
            }

            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
            // El BFF emite cookie bff_session HttpOnly. En prod el frontend
            // (libreriagabi.com etc.) vive en eTLD+1 distinto del BFF
            // (auth.libreriagabi.com), así que es cross-site: sin este header
            // el browser ni envía la cookie en la request ni acepta Set-Cookie
            // en la respuesta, aunque pongamos SameSite=None; Secure.
            header('Access-Control-Allow-Credentials: true');
        }

        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    }

    public static function sendSecurityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        header("Permissions-Policy: geolocation=(), microphone=(), camera=()");
        header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'; base-uri 'none'");

        // HSTS solo si la petición ya viene por HTTPS (de lo contrario el
        // navegador lo ignora y en local rompería el flujo de desarrollo).
        if (self::isHttpsRequest()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    public static function enforceProductionTransport(): void
    {
        $appEnv = strtolower((string) (env('APP_ENV') ?: 'local'));
        if (!in_array($appEnv, ['production', 'prod'], true)) {
            return;
        }

        if (!self::isHttpsRequest()) {
            Response::json(['error' => 'https required'], 400);
        }
    }

    public static function ensureStrongJwtSecret(): void
    {
        $secret = (string) (env('JWT_SECRET') ?: '');
        $appEnv = strtolower((string) (env('APP_ENV') ?: 'local'));

        $isWeak = $secret === '' || strlen($secret) < 32 || $secret === 'change-this-secret';
        if (!$isWeak) {
            return;
        }

        // En local se tolera el secret débil para no romper la DX, pero se
        // registra para que quede rastro. En cualquier otro entorno (staging,
        // production o valores desconocidos) la API se niega a arrancar:
        // tomar la decisión por el usuario aquí es lo más seguro.
        if ($appEnv === 'local') {
            error_log('JWT_SECRET débil en APP_ENV=local — válido solo para desarrollo.');
            return;
        }

        Response::json(['error' => 'jwt secret is not configured securely'], 500);
    }

    public static function getClientIp(): string
    {
        $candidates = [
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? null,
            $_SERVER['HTTP_X_FORWARDED_FOR'] ?? null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ];

        // Preferimos IPv4 sobre IPv6 para que los logs sean legibles en el
        // panel admin (un "2a02:9130:..." no ayuda a un admin no técnico).
        // Si la cabecera trae varios IPs (X-Forwarded-For), elegimos el
        // primer IPv4. Si no hay IPv4 en ninguna candidate, caemos al
        // primer IPv6 válido encontrado.
        $firstIpv6 = null;
        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || trim($candidate) === '') {
                continue;
            }
            $parts = array_map('trim', explode(',', $candidate));
            foreach ($parts as $part) {
                // IPv4 mapeado en IPv6 (`::ffff:1.2.3.4`) — Hostinger lo mete
                // en REMOTE_ADDR a veces. Extraemos la parte IPv4 a mano.
                if (preg_match('/^::ffff:(\d+\.\d+\.\d+\.\d+)$/i', $part, $m)
                    && filter_var($m[1], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    return $m[1];
                }
                if (filter_var($part, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    return $part;
                }
                if ($firstIpv6 === null && filter_var($part, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                    $firstIpv6 = $part;
                }
            }
        }

        return $firstIpv6 ?? 'unknown';
    }

    /**
     * Devuelve la URL base del frontend para un flujo de auth (reset de
     * contraseña, alerta de login, etc.). Prioriza el header Origin de la
     * request — si está en REDIRECT_ALLOWED_ORIGINS, se usa ese dominio
     * con la ruta fija correspondiente. Si no, se cae al env var de
     * fallback (mantiene el comportamiento previo a B2 y cubre peticiones
     * sin Origin como cronjobs o same-origin).
     *
     * Pensado para endpoints XHR iniciados desde el frontend (register,
     * request-password-reset, login) donde el navegador manda Origin.
     */
    public static function resolveFrontendUrl(string $fixedPath, string $envFallbackKey): string
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
        if (is_string($origin) && $origin !== ''
            && self::isAllowedAbsoluteUrl($origin, 'REDIRECT_ALLOWED_ORIGINS')) {
            return self::joinOriginAndPath($origin, $fixedPath);
        }
        return (string) (env($envFallbackKey) ?: '');
    }

    /**
     * Variante para cuando el origen viaja en el query string (caso del
     * verify email: el usuario llega desde su cliente de correo, la
     * request no lleva Origin, pero el link del email ya lo incluye como
     * `return_origin`). Siempre se valida contra la allowlist antes de
     * usarlo para evitar redirects abiertos.
     */
    public static function resolveFrontendUrlFromCandidate(?string $candidate, string $fixedPath, string $envFallbackKey): string
    {
        if (is_string($candidate) && $candidate !== ''
            && self::isAllowedAbsoluteUrl($candidate, 'REDIRECT_ALLOWED_ORIGINS')) {
            return self::joinOriginAndPath($candidate, $fixedPath);
        }
        return (string) (env($envFallbackKey) ?: '');
    }

    /**
     * Devuelve el Origin de la request si es un valor válido de la
     * allowlist; en caso contrario null. Útil para propagar el origen
     * entre flujos que no son 100% XHR (p.ej. incrustarlo en la URL de
     * un email cuyo clic luego llegará sin Origin).
     */
    public static function validatedRequestOrigin(): ?string
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
        if (is_string($origin) && $origin !== ''
            && self::isAllowedAbsoluteUrl($origin, 'REDIRECT_ALLOWED_ORIGINS')) {
            return $origin;
        }
        return null;
    }

    private static function joinOriginAndPath(string $origin, string $path): string
    {
        return rtrim($origin, '/') . '/' . ltrim($path, '/');
    }

    public static function isAllowedAbsoluteUrl(string $url, string $envKey): bool
    {
        if ($url === '') {
            return false;
        }

        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        $port = isset($parts['port']) ? (string) $parts['port'] : null;

        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            return false;
        }

        $allowed = self::parseCsv(env($envKey) ?: '');
        if ($allowed === []) {
            return true;
        }

        $origin = $scheme . '://' . $host . ($port !== null ? ':' . $port : '');
        return in_array($origin, $allowed, true);
    }

    private static function allowedOrigins(): array
    {
        $configured = self::parseCsv(env('CORS_ALLOWED_ORIGINS') ?: '');

        // Allowlist mínima HARDCODED de prod: si el FTP de Hostinger
        // sobrescribe el .env (bug documentado en feedback_deploy_env_restore.md),
        // CORS sigue funcionando para los dominios reales y evitamos cortes
        // del servicio en cliente sin que nos enteremos hasta que alguien
        // reporta. Cualquier dominio extra (sites de prueba, hostingersite.com
        // temporales) debe ir igualmente en CORS_ALLOWED_ORIGINS del .env.
        $prodFallback = [
            'https://libreria-taupe.vercel.app',
            'https://www.libreria-taupe.vercel.app',
        ];

        if ($configured !== []) {
            // Si hay .env, lo respetamos pero seguimos garantizando los apex
            // prod por si el .env fue editado a mano y se olvidó alguno
            // (caso típico al añadir un dominio nuevo y borrar el viejo).
            return array_values(array_unique(array_merge($configured, $prodFallback)));
        }

        return array_merge($prodFallback, [
            'http://localhost:5173',
            'http://localhost:5174',
            'http://localhost:5175',
            'http://localhost:5176',
            'http://localhost:5177',
            'http://127.0.0.1:5173',
            'http://127.0.0.1:5174',
            'http://127.0.0.1:5175',
            'http://127.0.0.1:5176',
            'http://127.0.0.1:5177',
        ]);
    }

    private static function parseCsv(string $value): array
    {
        $parts = array_map('trim', explode(',', $value));
        $parts = array_values(array_filter($parts, static fn($item) => $item !== ''));
        return array_values(array_unique($parts));
    }

    private static function isHttpsRequest(): bool
    {
        $https = strtolower((string) ($_SERVER['HTTPS'] ?? ''));
        if ($https === 'on' || $https === '1') {
            return true;
        }

        $scheme = strtolower((string) ($_SERVER['REQUEST_SCHEME'] ?? ''));
        if ($scheme === 'https') {
            return true;
        }

        $forwardedProto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
        return $forwardedProto === 'https';
    }

    /**
     * Defensa CSRF para endpoints BFF que mutan estado.
     *
     * Contexto: la cookie `bff_session` va con `SameSite=None; Secure` porque
     * el BFF vive en `auth.libreriagabi.com` y los SPAs en otros eTLD+1. Eso
     * significa que el navegador la envía en CUALQUIER petición cross-site —
     * incluidas las que origine `evil.com` con `credentials:'include'`. Sin
     * defensa adicional, un atacante podría disparar acciones autenticadas en
     * nombre del usuario logueado.
     *
     * Esta función valida Origin/Referer contra REDIRECT_ALLOWED_ORIGINS para
     * todo POST/PUT/PATCH/DELETE a /bff/*. Si el origen es legítimo, sigue;
     * si no, responde 403 y aborta.
     *
     * Reglas:
     * - Solo aplica a métodos no idempotentes (POST/PUT/PATCH/DELETE).
     * - Solo aplica a rutas que empiezan por /bff/.
     * - Prioriza Origin (el navegador lo manda siempre en cross-site).
     * - Si no hay Origin (algunos casos same-origin antiguos), fallback a Referer.
     * - Si no hay ninguno, rechaza: un POST cross-site sin Origin ni Referer
     *   es sospechoso (clientes legítimos del ecosistema siempre lo envían).
     *
     * El testing local con curl tendrá que añadir
     *   `-H "Origin: http://localhost:5178"`
     * para llamar a endpoints POST del BFF — comportamiento esperado.
     */
    public static function enforceBffCsrf(): void
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        if (!in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return;
        }

        $uri = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
        if (strpos($uri, '/bff/') !== 0) {
            return;
        }

        $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        if ($origin !== '') {
            if (!self::isAllowedAbsoluteUrl($origin, 'REDIRECT_ALLOWED_ORIGINS')) {
                Response::json([
                    'error'  => 'csrf_origin_not_allowed',
                    'detail' => 'Origin not in REDIRECT_ALLOWED_ORIGINS.',
                ], 403);
            }
            return;
        }

        // Sin Origin → intentamos validar Referer. Es menos fiable (el usuario
        // puede tener Referer-Policy: no-referrer global), pero es lo único
        // disponible si el browser no manda Origin (raro hoy, pero posible).
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if ($referer !== '') {
            // isAllowedAbsoluteUrl normaliza scheme://host[:port] del referer
            // contra la allowlist. Aunque el referer trae path/query, parse_url
            // extrae solo el host.
            if (!self::isAllowedAbsoluteUrl($referer, 'REDIRECT_ALLOWED_ORIGINS')) {
                Response::json([
                    'error'  => 'csrf_referer_not_allowed',
                    'detail' => 'Referer not in REDIRECT_ALLOWED_ORIGINS.',
                ], 403);
            }
            return;
        }

        Response::json([
            'error'  => 'csrf_missing_origin',
            'detail' => 'POST/PUT/PATCH/DELETE to /bff/* requires Origin or Referer header.',
        ], 403);
    }
}
