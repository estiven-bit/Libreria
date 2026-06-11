<?php

require_once __DIR__ . '/../oauth2/Repositories/UserRepository.php';

use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Factory\Psr17Factory;
use LibreriaGabi\OAuth2\Repositories\UserRepository;
use LibreriaGabi\OAuth2\ServerFactory;

/**
 * Backend-for-Frontend (BFF) compartido para los clientes OIDC del ecosistema.
 *
 * Modelo multi-cuenta: cada sesión BFF puede contener tokens de varias cuentas
 * del mismo usuario del navegador (estilo Google account picker). El estado:
 *
 *   $_SESSION['oauth_by_user'] = [
 *     <user_id> => ['access_token', 'refresh_token', 'id_token', 'client_id',
 *                   'expires_at', 'claims_cache'],
 *     ...
 *   ]
 *   $_SESSION['oauth_active_user'] = <user_id>   // cuenta activa
 *   $_SESSION['oauth_pkce'][<state>] = [...]      // PKCE state pendiente
 *
 * Los endpoints sin sufijo (/bff/me, /bff/logout) operan sobre la cuenta activa.
 * Los nuevos /bff/accounts, /bff/switch, /bff/logout-one gestionan el set.
 */
class BffController
{
    /** @var array<string, array|null> */
    private static array $clientCache = [];

    public static function health(): void
    {
        Response::json(['ok' => true, 'ts' => time()]);
    }

    private static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Sesión persistente: la cookie y el fichero server-side viven
            // 30 días, en sync con el TTL del refresh_token (DateInterval P1M
            // en ServerFactory). Así la UX es "logueado hasta logout manual"
            // mientras el usuario tenga actividad ≤ 30 días entre visitas.
            // Si subimos el refresh_token TTL, alinear este valor también.
            $bffLifetime = 30 * 24 * 60 * 60; // 30d
            ini_set('session.gc_maxlifetime', (string)$bffLifetime);
            session_set_cookie_params([
                'lifetime' => $bffLifetime,
                'path' => '/',
                'httponly' => true,
                // Cross-site: el frontend vive en libreria-taupe.vercel.app /
                // y el BFF en la URL de backend (eTLD+1 distinto). Cookies cross-site requieren
                // (eTLD+1 distinto). Cookies cross-site requieren
                // SameSite=None; Secure. En dev (localhost:5178 → :8000)
                // también aplica: orígenes distintos por puerto + Chrome/FF
                // aceptan Secure sobre HTTP cuando es localhost.
                'samesite' => 'None',
                'secure' => true,
            ]);
            session_name('bff_session');
            session_start();
        }
    }

    private static function lookupClient(string $clientId): ?array
    {
        if (array_key_exists($clientId, self::$clientCache)) {
            return self::$clientCache[$clientId];
        }

        $stmt = Database::connect()->prepare(
            'SELECT id, redirect_uri FROM oauth_clients WHERE id = :id AND is_revoked = 0'
        );
        $stmt->execute([':id' => $clientId]);
        $row = $stmt->fetch();
        self::$clientCache[$clientId] = $row ?: null;
        return $row ?: null;
    }

    /**
     * Decodifica el sub claim de un id_token (JWT) sin verificar firma.
     * Solo lo usamos para identificar al usuario tras el callback — el token
     * ya viene firmado por nosotros mismos, así que confiamos en él.
     */
    private static function userIdFromIdToken(string $idToken): int
    {
        $parts = explode('.', $idToken);
        if (count($parts) < 2) return 0;
        $padded = strtr($parts[1], '-_', '+/');
        $padded .= str_repeat('=', (4 - strlen($padded) % 4) % 4);
        $payload = json_decode(base64_decode($padded), true);
        return (int)($payload['sub'] ?? 0);
    }

    /**
     * Genera code_verifier + state PKCE, los guarda en sesión, y devuelve la
     * URL del IdP a la que el frontend debe navegar (full page redirect).
     *
     * Si force=1, añade prompt=login al authorize_url para que el IdP fuerce
     * el formulario aunque haya sesión activa. Esto es lo que usa "Añadir cuenta".
     */
    public static function start(): void
    {
        self::startSession();
        $clientId = (string)($_GET['client'] ?? '');
        $force = !empty($_GET['force']);
        $client = self::lookupClient($clientId);
        if (!$client) {
            Response::json(['error' => 'unknown_client'], 400);
        }

        $verifier = rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
        $state = bin2hex(random_bytes(16));

        $_SESSION['oauth_pkce'][$state] = [
            'verifier' => $verifier,
            'client_id' => $client['id'],
            'redirect_uri' => $client['redirect_uri'],
            'created_at' => time(),
        ];

        $params = [
            'response_type' => 'code',
            'client_id' => $client['id'],
            'redirect_uri' => $client['redirect_uri'],
            'scope' => 'openid profile email',
            'state' => $state,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ];
        if ($force) {
            $params['prompt'] = 'login';
        }
        $authorizeUrl = ServerFactory::issuer() . '/oauth/authorize?' . http_build_query($params);

        Response::json(['authorize_url' => $authorizeUrl, 'state' => $state]);
    }

    /**
     * El frontend, tras volver del IdP a /oidc-callback?code=...&state=...,
     * envía esos params aquí. Validamos state, canjeamos code por tokens
     * y guardamos los tokens indexados por user_id (sub del id_token).
     */
    public static function callback(): void
    {
        self::startSession();
        $code = (string)($_GET['code'] ?? '');
        $state = (string)($_GET['state'] ?? '');

        if (!$code) {
            Response::json(['error' => 'invalid_state_or_code'], 400);
        }
        $pending = $_SESSION['oauth_pkce'][$state] ?? null;
        if (!$pending) {
            // El state no está en bff_session. Causa típica: el usuario abrió
            // el email de verificación en OTRO dispositivo / OTRO navegador,
            // o la sesión bff caducó entre /bff/start y este callback
            // (verificación de email tarda minutos; GC de sesión, cierre de
            // pestaña). El IdP ya completó la autorización (tenemos `code`)
            // y la idp_session del usuario está viva, así que el frontend
            // puede reintentar /bff/start: el segundo authorize será un
            // instant-redirect y obtendrá un nuevo `code` consumible.
            Response::json([
                'error' => 'state_missing_retry',
                'message' => 'Sesión de login expirada. Reintentando…',
            ], 409);
        }
        unset($_SESSION['oauth_pkce'][$state]);
        if ((time() - (int)$pending['created_at']) > 600) {
            Response::json(['error' => 'state_expired'], 400);
        }

        try {
            $tokenResp = self::exchangeCodeInternal([
                'grant_type' => 'authorization_code',
                'client_id' => $pending['client_id'],
                'redirect_uri' => $pending['redirect_uri'],
                'code' => $code,
                'code_verifier' => $pending['verifier'],
            ]);
        } catch (\Throwable $e) {
            Response::json(['error' => 'token_exchange_failed', 'detail' => $e->getMessage()], 502);
        }
        if (!isset($tokenResp['access_token'])) {
            Response::json(['error' => 'token_exchange_failed', 'detail' => $tokenResp], 502);
        }

        $userId = isset($tokenResp['id_token'])
            ? self::userIdFromIdToken($tokenResp['id_token'])
            : 0;
        if ($userId <= 0) {
            Response::json(['error' => 'no_user_in_id_token'], 502);
        }

        // Cacheamos claims también, para no tener que ir a BD cada vez que se
        // pinta el selector de cuentas.
        $user = UserRepository::findById($userId);
        $claims = $user ? $user->claims : null;

        if (!isset($_SESSION['oauth_by_user']) || !is_array($_SESSION['oauth_by_user'])) {
            $_SESSION['oauth_by_user'] = [];
        }
        $_SESSION['oauth_by_user'][$userId] = [
            'access_token' => $tokenResp['access_token'],
            'refresh_token' => $tokenResp['refresh_token'] ?? null,
            'id_token' => $tokenResp['id_token'] ?? null,
            'client_id' => $pending['client_id'],
            'expires_at' => time() + (int)($tokenResp['expires_in'] ?? 3600),
            'claims_cache' => $claims,
        ];
        $_SESSION['oauth_active_user'] = $userId;
        session_regenerate_id(true);

        Response::json([
            'ok' => true,
            'authenticated' => true,
            'user' => $claims,
        ]);
    }

    /**
     * Devuelve los claims del usuario activo. Si el access_token expiró o no
     * hay sesión, devuelve authenticated:false.
     */
    public static function me(): void
    {
        self::startSession();
        $session = self::activeAccount();
        if (!$session) {
            Response::json(['authenticated' => false], 200);
        }
        if (time() >= ($session['expires_at'] ?? 0)) {
            $refreshed = self::tryRefreshSession((int)$_SESSION['oauth_active_user'], $session);
            if (!$refreshed) {
                self::removeAccount((int)$_SESSION['oauth_active_user']);
                Response::json(['authenticated' => false, 'reason' => 'expired'], 200);
            }
            $session = $refreshed;
        }

        try {
            $resourceServer = ServerFactory::resourceServer();
            $psr17 = new Psr17Factory();
            $request = $psr17->createServerRequest('GET', '/oauth/userinfo')
                ->withHeader('Authorization', 'Bearer ' . $session['access_token']);
            $request = $resourceServer->validateAuthenticatedRequest($request);
            $userId = (int)$request->getAttribute('oauth_user_id');

            $claims = $session['claims_cache'] ?? null;
            if (!$claims) {
                $user = UserRepository::findById($userId);
                if (!$user) {
                    self::removeAccount($userId);
                    Response::json(['authenticated' => false, 'reason' => 'user_not_found'], 200);
                }
                $claims = $user->claims;
                if (isset($_SESSION['oauth_by_user'][$userId])) {
                    $_SESSION['oauth_by_user'][$userId]['claims_cache'] = $claims;
                }
            }

            // Throttle last_seen: como máximo 1 UPDATE/min por sesión BFF.
            $accessTokenId = (string)$request->getAttribute('oauth_access_token_id');
            $lastBump = (int)($_SESSION['last_seen_bump'] ?? 0);
            if ($accessTokenId !== '' && (time() - $lastBump) >= 60) {
                $upd = Database::connect()->prepare(
                    'UPDATE user_sessions us
                     INNER JOIN oauth_access_tokens at ON at.session_id = us.id
                     SET us.last_seen = NOW()
                     WHERE at.id = :tid AND us.revoked_at IS NULL'
                );
                $upd->execute([':tid' => $accessTokenId]);
                $_SESSION['last_seen_bump'] = time();
            }
            Response::json(['authenticated' => true, 'user' => $claims]);
        } catch (OAuthServerException $e) {
            self::removeAccount((int)$_SESSION['oauth_active_user']);
            Response::json(['authenticated' => false, 'reason' => 'invalid_token'], 200);
        }
    }

    /**
     * Lista todas las cuentas autenticadas en esta sesión BFF.
     * Devuelve [{id, name, email, role, is_active}, ...].
     */
    public static function accounts(): void
    {
        self::startSession();
        $byUser = $_SESSION['oauth_by_user'] ?? [];
        $activeId = (int)($_SESSION['oauth_active_user'] ?? 0);

        $accounts = [];
        foreach ($byUser as $userId => $data) {
            $claims = $data['claims_cache'] ?? null;
            // Si no tenemos claims cacheados (tokens viejos), los pedimos a BD
            if (!$claims) {
                $user = UserRepository::findById((int)$userId);
                $claims = $user ? $user->claims : null;
                if ($claims) {
                    $_SESSION['oauth_by_user'][$userId]['claims_cache'] = $claims;
                }
            }
            if (!$claims) continue;
            $accounts[] = [
                'id' => (int)$userId,
                'name' => $claims['name'] ?? '',
                'email' => $claims['email'] ?? '',
                'role' => $claims['role'] ?? 'user',
                'is_active' => ((int)$userId === $activeId),
            ];
        }
        Response::json(['accounts' => $accounts]);
    }

    /**
     * Cambia la cuenta activa de la sesión BFF. El user_id debe estar ya en
     * oauth_by_user (es decir, esa cuenta debe haber pasado por callback antes).
     */
    public static function switchAccount(): void
    {
        self::startSession();
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $userId = (int)($body['user'] ?? $_GET['user'] ?? 0);
        if ($userId <= 0 || !isset($_SESSION['oauth_by_user'][$userId])) {
            Response::json(['error' => 'unknown_account'], 400);
        }
        $_SESSION['oauth_active_user'] = $userId;
        Response::json(['ok' => true]);
    }

    /**
     * Cierra una cuenta concreta de la sesión BFF. Si era la activa, promociona
     * cualquier otra. Si no quedaba ninguna, la sesión BFF queda vacía y
     * authenticated:false.
     */
    public static function logoutOne(): void
    {
        self::startSession();
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $userId = (int)($body['user'] ?? $_GET['user'] ?? 0);
        if ($userId <= 0 || !isset($_SESSION['oauth_by_user'][$userId])) {
            Response::json(['error' => 'unknown_account'], 400);
        }
        self::removeAccount($userId);
        Response::json(['ok' => true]);
    }

    /**
     * Cierra TODA la sesión BFF (todas las cuentas).
     */
    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();
        Response::json(['ok' => true]);
    }

    /**
     * Devuelve el bloque de tokens del usuario activo, o null si no hay.
     */
    private static function activeAccount(): ?array
    {
        $byUser = $_SESSION['oauth_by_user'] ?? [];
        $activeId = (int)($_SESSION['oauth_active_user'] ?? 0);
        if ($activeId <= 0 || !isset($byUser[$activeId])) return null;
        return $byUser[$activeId];
    }

    /**
     * Silent refresh: si la sesión BFF tiene refresh_token, lo intercambia
     * por un nuevo access_token (grant_type=refresh_token) y actualiza
     * $_SESSION['oauth_by_user'][$userId]. Devuelve el bloque actualizado.
     *
     * UX target: el usuario permanece logueado "para siempre" hasta logout
     * manual mientras el refresh_token (TTL 1 mes en ServerFactory) siga
     * vivo. Si el refresh también expiró o el servidor lo revocó, devuelve
     * null y el caller debe tratar la sesión como expirada.
     *
     * Acoplado a `activeAccount`: el caller pasa el bloque obtenido de
     * `activeAccount()` para que no haga falta releer $_SESSION dos veces.
     */
    private static function tryRefreshSession(int $userId, array $session): ?array
    {
        $rt = (string)($session['refresh_token'] ?? '');
        if ($rt === '') return null;

        try {
            $tokenResp = self::exchangeCodeInternal([
                'grant_type' => 'refresh_token',
                'refresh_token' => $rt,
                'client_id' => (string)($session['client_id'] ?? ''),
            ]);
        } catch (\Throwable $e) {
            error_log('[bff.refresh] grant failed for user=' . $userId . ': ' . $e->getMessage());
            return null;
        }
        if (empty($tokenResp['access_token'])) return null;

        $updated = [
            'access_token'  => $tokenResp['access_token'],
            // RefreshTokenGrant rota el refresh_token en cada uso; si por algún
            // motivo no llega uno nuevo, mantenemos el anterior (no debería pasar).
            'refresh_token' => $tokenResp['refresh_token'] ?? $rt,
            'id_token'      => $tokenResp['id_token'] ?? ($session['id_token'] ?? null),
            'client_id'     => $session['client_id'] ?? '',
            'expires_at'    => time() + (int)($tokenResp['expires_in'] ?? 3600),
            'claims_cache'  => $session['claims_cache'] ?? null,
        ];
        $_SESSION['oauth_by_user'][$userId] = $updated;
        return $updated;
    }

    /**
     * Quita una cuenta del set y promociona otra como activa si era la actual.
     * Si no queda ninguna, limpia oauth_active_user.
     */
    private static function removeAccount(int $userId): void
    {
        unset($_SESSION['oauth_by_user'][$userId]);
        if ((int)($_SESSION['oauth_active_user'] ?? 0) === $userId) {
            $remaining = array_keys($_SESSION['oauth_by_user'] ?? []);
            $_SESSION['oauth_active_user'] = $remaining[0] ?? null;
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    //  ENDPOINTS ADMIN — solo accesibles desde el BFF si el usuario activo
    //  tiene role='admin'. Operaciones sensibles (role change, ban, force
    //  logout) requieren re-verificación de la contraseña del admin.
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Valida que el usuario activo de la sesión BFF existe, su access_token es
     * válido, y tiene role='admin'. Devuelve la fila users del admin.
     * Si algo falla, responde 401/403 y termina.
     */
    private static function verifyAdminAuth(): array
    {
        self::startSession();
        $session = self::activeAccount();
        if (!$session) Response::json(['error' => 'unauthenticated'], 401);
        if (time() >= ($session['expires_at'] ?? 0)) {
            $refreshed = self::tryRefreshSession((int)$_SESSION['oauth_active_user'], $session);
            if (!$refreshed) Response::json(['error' => 'session_expired'], 401);
            $session = $refreshed;
        }

        try {
            $resourceServer = ServerFactory::resourceServer();
            $psr17 = new Psr17Factory();
            $req = $psr17->createServerRequest('GET', '/oauth/userinfo')
                ->withHeader('Authorization', 'Bearer ' . $session['access_token']);
            $req = $resourceServer->validateAuthenticatedRequest($req);
            $userId = (int)$req->getAttribute('oauth_user_id');
        } catch (OAuthServerException $e) {
            Response::json(['error' => 'invalid_token'], 401);
        }

        $stmt = Database::connect()->prepare(
            'SELECT id, email, name, role, password FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $userId]);
        $admin = $stmt->fetch();
        if (!$admin) Response::json(['error' => 'user_not_found'], 401);
        if ($admin['role'] !== 'admin') Response::json(['error' => 'forbidden'], 403);
        return $admin;
    }

    /**
     * Re-verifica la contraseña del admin para operaciones sensibles. Si falla,
     * responde 401 y termina la request.
     */
    private static function verifyAdminPassword(array $admin, string $password): void
    {
        if ($password === '' || !password_verify($password, $admin['password'])) {
            Response::json(['error' => 'wrong_password'], 401);
        }
    }

    /**
     * GET /bff/admin/users — lista de usuarios para el panel admin.
     */
    public static function adminListUsers(): void
    {
        self::verifyAdminAuth();
        // JOIN con user_sessions para incluir el last_seen de la sesión más
        // reciente no-revocada. El subquery filtra por revoked_at IS NULL y
        // se queda con el último por usuario.
        $rows = Database::connect()->query(
            'SELECT u.id, u.username, u.email, u.name, u.first_name, u.last_name, u.phone, u.role,
                    u.is_email_verified, u.email_verified_at, u.banned_at, u.created_at,
                    (SELECT MAX(last_seen) FROM user_sessions
                     WHERE user_id = u.id AND revoked_at IS NULL) AS last_seen
             FROM users u
             ORDER BY u.created_at DESC, u.id DESC'
        )->fetchAll();
        // MySQL devuelve DATETIME/TIMESTAMP como string plano "YYYY-MM-DD HH:MM:SS"
        // sin offset. En Hostinger el server corre UTC, así que esos strings son
        // UTC pero el frontend los parseaba como hora local (CEST) → "Hace 2 h"
        // constante. Normalizamos a ISO 8601 con sufijo Z para que el browser
        // los interprete bien independiente de su zona.
        foreach ($rows as &$r) {
            foreach (['last_seen', 'created_at', 'email_verified_at', 'banned_at'] as $f) {
                if (!empty($r[$f])) $r[$f] = str_replace(' ', 'T', $r[$f]) . 'Z';
            }
        }
        unset($r);
        Response::json(['users' => $rows]);
    }

    /**
     * POST /bff/admin/update-role — cambia el role de un usuario. Body:
     *   {user_id, role, current_password}
     * Roles válidos: user, admin, real. No puede demotarse al último admin.
     */
    public static function adminUpdateRole(): void
    {
        $admin = self::verifyAdminAuth();
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $targetId = (int)($body['user_id'] ?? 0);
        $newRole = (string)($body['role'] ?? '');
        $password = (string)($body['current_password'] ?? '');

        if ($targetId <= 0) Response::json(['error' => 'invalid_user_id'], 400);
        if (!in_array($newRole, ['user', 'admin', 'real', 'empleado'], true)) {
            Response::json(['error' => 'invalid_role'], 400);
        }
        self::verifyAdminPassword($admin, $password);

        $db = Database::connect();
        // Salvaguarda: no permitir que se quede el sistema sin ningún admin.
        if ($newRole !== 'admin') {
            $stmt = $db->prepare('SELECT role FROM users WHERE id = :id LIMIT 1');
            $stmt->execute([':id' => $targetId]);
            $current = $stmt->fetch();
            if ($current && $current['role'] === 'admin') {
                $count = (int)$db->query("SELECT COUNT(*) AS n FROM users WHERE role = 'admin'")
                    ->fetch()['n'];
                if ($count <= 1) {
                    Response::json(['error' => 'last_admin'], 409);
                }
            }
        }

        $upd = $db->prepare('UPDATE users SET role = :role WHERE id = :id');
        $upd->execute([':role' => $newRole, ':id' => $targetId]);
        Response::json(['ok' => true]);
    }

    /**
     * POST /bff/admin/set-ban — banear o desbanear un usuario. Body:
     *   {user_id, banned: bool, current_password}
     * Al banear, revocamos TODAS sus sesiones para echarlo de todas las apps.
     */
    public static function adminSetBan(): void
    {
        $admin = self::verifyAdminAuth();
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $targetId = (int)($body['user_id'] ?? 0);
        $banned = (bool)($body['banned'] ?? false);
        $password = (string)($body['current_password'] ?? '');

        if ($targetId <= 0) Response::json(['error' => 'invalid_user_id'], 400);
        if ($targetId === (int)$admin['id']) {
            Response::json(['error' => 'cannot_ban_self'], 409);
        }
        self::verifyAdminPassword($admin, $password);

        $db = Database::connect();
        if ($banned) {
            $upd = $db->prepare('UPDATE users SET banned_at = NOW() WHERE id = :id');
            $upd->execute([':id' => $targetId]);
            $rev = $db->prepare(
                'UPDATE user_sessions SET revoked_at = NOW()
                 WHERE user_id = :uid AND revoked_at IS NULL'
            );
            $rev->execute([':uid' => $targetId]);
        } else {
            $upd = $db->prepare('UPDATE users SET banned_at = NULL WHERE id = :id');
            $upd->execute([':id' => $targetId]);
        }
        Response::json(['ok' => true]);
    }

    /**
     * POST /bff/admin/force-logout — revoca TODAS las sesiones activas del
     * usuario destino, forzando relogin en todas sus apps. Body:
     *   {user_id, current_password}
     */
    public static function adminForceLogout(): void
    {
        $admin = self::verifyAdminAuth();
        $body = json_decode(file_get_contents('php://input'), true) ?: [];
        $targetId = (int)($body['user_id'] ?? 0);
        $password = (string)($body['current_password'] ?? '');

        if ($targetId <= 0) Response::json(['error' => 'invalid_user_id'], 400);
        self::verifyAdminPassword($admin, $password);

        $rev = Database::connect()->prepare(
            'UPDATE user_sessions SET revoked_at = NOW()
             WHERE user_id = :uid AND revoked_at IS NULL'
        );
        $rev->execute([':uid' => $targetId]);
        Response::json(['ok' => true]);
    }

    /**
     * POST /bff/admin/sync-notion — sincroniza la lista de usuarios a la
     * BBDD de Notion configurada en NOTION_DATABASE_ID. Devuelve totales.
     */
    public static function adminSyncNotion(): void
    {
        self::verifyAdminAuth();
        if (!class_exists('NotionService')) {
            Response::json(['error' => 'notion_service_missing'], 500);
        }
        $result = NotionService::syncAllUsers();
        Response::json($result);
    }

    /**
     * Proxy genérico /bff/<app>/<path> → BACKEND PHP de cada app del ecosistema.
     *
     * El frontend de cada app llama aquí en vez de hablar con su BACKEND
     * directamente; así el access_token nunca sale del servidor. Validamos
     * la sesión OIDC, mapeamos <path> a un .php concreto (guiones → underscores)
     * y reenviamos la petición con cabeceras firmadas con BFF_SERVICE_SECRET.
     *
     * El BACKEND solo procesa la petición si ese secret coincide; así nadie
     * de fuera puede falsificar headers X-LibreriaGabi-Role: admin.
     *
     * Si el primer segmento del path es "admin-" o "admin/" exigimos role=admin
     * antes de tocar el BACKEND, igual que en /bff/admin/*.
     */
    public static function proxyToAppBackend(string $app, string $path, string $method): void
    {
        $backendUrl = self::backendUrlFor($app);
        if ($backendUrl === null) Response::json(['error' => 'unknown_app'], 404);

        // Reglas de auth por app:
        // Todo lo que llegue a endpoints de administración exige admin.
        $requiresAdmin = str_starts_with($path, 'admin-')
            || str_starts_with($path, 'admin/')
            || str_contains($path, '/admin/');
        $user = null;
        if ($requiresAdmin) {
            $user = self::verifyAdminAuth();
        } else {
            // Verificación opcional de autenticación para endpoints no-admin
            self::startSession();
            $session = self::activeAccount();
            if ($session) {
                if (time() >= ($session['expires_at'] ?? 0)) {
                    $refreshed = self::tryRefreshSession((int)$_SESSION['oauth_active_user'], $session);
                    if ($refreshed) {
                        $session = $refreshed;
                    } else {
                        $session = null;
                    }
                }
                if ($session) {
                    try {
                        $resourceServer = ServerFactory::resourceServer();
                        $psr17 = new Psr17Factory();
                        $req = $psr17->createServerRequest('GET', '/oauth/userinfo')
                            ->withHeader('Authorization', 'Bearer ' . $session['access_token']);
                        $req = $resourceServer->validateAuthenticatedRequest($req);
                        $userId = (int)$req->getAttribute('oauth_user_id');
                        $stmt = Database::connect()->prepare(
                            'SELECT id, email, name, role FROM users WHERE id = :id LIMIT 1'
                        );
                        $stmt->execute([':id' => $userId]);
                        $userRow = $stmt->fetch();
                        if ($userRow) {
                            $user = $userRow;
                        }
                    } catch (\Throwable $e) {
                        $user = null;
                    }
                }
            }
        }

        // Dos modos de mapeo path → URL del backend:
        //
        //   (a) Passthrough: el path ya parece una ruta real (contiene '/' o
        //       termina en '.php'). Lo reenviamos tal cual al backend, solo
        //       sanitizando segmentos para evitar path traversal.
        //       Caso típico: Inmobiliaria, donde los endpoints viven en
        //       backend/api/<algo>.php y el frontend ya manda el path completo.
        //
        //   (b) Convención "kebab → snake": el path es un slug plano
        //       (ej. "admin-list"). Lo convertimos a "admin_list.php".
        //       Caso típico: Energy / Ingeniería con endpoints flat.
        $isPassthrough = str_contains($path, '/') || str_ends_with($path, '.php') || $app === 'libreriagabi';
        if ($isPassthrough) {
            // Sanitiza cada segmento para bloquear ".." y caracteres raros,
            // pero conserva la jerarquía con '/'.
            $segments = [];
            foreach (explode('/', $path) as $seg) {
                if ($seg === '' || $seg === '.' || $seg === '..') continue;
                $segments[] = preg_replace('/[^a-z0-9_.\-]/i', '', $seg);
            }
            $cleanPath = implode('/', $segments);
            if ($cleanPath === '') Response::json(['error' => 'invalid_path'], 400);
            // Garantizamos extensión .php (si el cliente la omitió), excepto para Librería Gabi
            if ($app !== 'libreriagabi' && !str_ends_with($cleanPath, '.php')) {
                $cleanPath .= '.php';
            }
            $targetUrl = rtrim($backendUrl, '/') . '/' . $cleanPath;
        } else {
            $base = preg_replace('/[^a-z0-9_]/i', '', str_replace(['-', '/'], '_', $path));
            if ($base === '' || $base === null) Response::json(['error' => 'invalid_path'], 400);
            $targetUrl = rtrim($backendUrl, '/') . '/' . $base . '.php';
        }
        if (!empty($_SERVER['QUERY_STRING'])) {
            $targetUrl .= '?' . $_SERVER['QUERY_STRING'];
        }

        $serviceSecret = env('BFF_SERVICE_SECRET');
        if (!$serviceSecret) {
            $envFile = __DIR__ . '/../.env';
            if (is_file($envFile)) {
                foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    if (preg_match('/^BFF_SERVICE_SECRET\s*=\s*(.+)$/', trim($line), $m)) {
                        $serviceSecret = trim($m[1]);
                        break;
                    }
                }
            }
        }
        if (!$serviceSecret) Response::json(['error' => 'service_secret_not_configured'], 500);

        $contentType = $_SERVER['CONTENT_TYPE'] ?? 'application/json';
        $isMultipart = stripos($contentType, 'multipart/form-data') === 0;
        // En v2.0 el backend recibe el request DESDE el BFF (server-to-server),
        // así que su REMOTE_ADDR es la IP del IdP. Para que el audit log del
        // backend muestre la IP real del usuario, la mandamos en una cabecera
        // dedicada. Usamos Security::getClientIp() que prefiere IPv4 sobre
        // IPv6 (logs más legibles).
        $clientIp = Security::getClientIp();

        // Valores que se inyectan en las cabeceras X-LibreriaGabi-* si hay usuario logueado.
        $bffUser  = $user ? (string)(int)$user['id'] : '';
        $bffRole  = $user ? (string)($user['role'] ?? 'user') : '';
        $bffEmail = $user ? (string)($user['email'] ?? '') : '';
        $bffName  = $user ? (string)($user['name'] ?? '') : '';

        $body = null;
        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            if ($isMultipart) {
                // PHP ya parseó el body en $_POST + $_FILES; php://input está
                // VACÍO en multipart (limitación documentada en PHP). Si
                // forwardeáramos php://input, el backend vería $_FILES vacío
                // y devolvería 422 "no se recibió ningún archivo".
                // Reconstruimos el body como array asociativo y curl
                // genera el multipart nuevo automáticamente.
                $multipart = [];
                foreach ($_POST as $k => $v) {
                    $multipart[$k] = $v;
                }
                foreach ($_FILES as $k => $f) {
                    if (($f['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK
                        && is_uploaded_file((string)$f['tmp_name'])) {
                        $multipart[$k] = new CURLFile(
                            (string)$f['tmp_name'],
                            (string)($f['type'] ?? ''),
                            (string)($f['name'] ?? 'file')
                        );
                    }
                }
                $body = $multipart;
            } else {
                $body = file_get_contents('php://input');
            }
        }

        // A1 — Firma HMAC + timestamp (hardening 2026-05-22).
        // Sin esto, el `X-LibreriaGabi-Service-Secret` estático es susceptible a:
        //   - Forge: si el secret se filtra, un atacante puede fabricar peticiones
        //     con cualquier X-LibreriaGabi-Role: admin sin tener acceso al BFF.
        //   - Replay: una petición capturada puede reproducirse N veces sin caducar.
        // La firma vincula esta petición CONCRETA (con este body, ts, user, role)
        // y caduca tras una ventana corta.
        //
        // Cadena canónica (debe coincidir bit a bit con la del backend):
        //   <timestamp>\n<user_id>\n<role>\n<email>\n<sha256_hex(body_raw)>
        //
        // Body para el hash:
        //   - JSON/urlencoded: $body es el string raw que recibió el BFF.
        //   - multipart: el backend verá php://input VACÍO (PHP no lo expone en
        //     multipart), así que hasheamos '' aquí también para que coincida.
        $headers = [
            'X-LibreriaGabi-Client-IP: ' . $clientIp,
            'X-LibreriaGabi-Service-Secret: ' . $serviceSecret,
        ];

        if ($user) {
            $timestamp     = time();
            $bodyForHash   = $isMultipart ? '' : (string)$body;
            $bodyHash      = hash('sha256', $bodyForHash);
            $canonical     = $timestamp . "\n" . $bffUser . "\n" . $bffRole . "\n" . $bffEmail . "\n" . $bodyHash;
            $signature     = hash_hmac('sha256', $canonical, $serviceSecret);

            $headers[] = 'X-LibreriaGabi-User: ' . $bffUser;
            $headers[] = 'X-LibreriaGabi-Role: ' . $bffRole;
            $headers[] = 'X-LibreriaGabi-Email: ' . $bffEmail;
            $headers[] = 'X-LibreriaGabi-Name: ' . $bffName;
            $headers[] = 'X-LibreriaGabi-Timestamp: ' . $timestamp;
            $headers[] = 'X-LibreriaGabi-Signature: ' . $signature;
        }
        // Para JSON / urlencoded conservamos el Content-Type original. Para
        // multipart NO lo añadimos: curl genera su propio Content-Type con un
        // boundary distinto al original al construir el form-data desde el
        // array CURLFile (ver bloque siguiente).
        if (!$isMultipart) {
            $headers[] = 'Content-Type: ' . $contentType;
        }

        $ch = curl_init($targetUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADER => true,
            // 100s = margen máximo antes de que Cloudflare (delante de Hostinger
            // en planes Free) corte la conexión por su cuenta. Los endpoints
            // pesados (generar_ia.php llamando a Anthropic, etc.) necesitan
            // este margen; los rápidos siguen respondiendo en ~100ms.
            CURLOPT_TIMEOUT => 100,
        ]);
        if ($body !== null && $body !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $rawResponse = curl_exec($ch);
        if ($rawResponse === false) {
            $err = curl_error($ch);
            curl_close($ch);
            Response::json(['error' => 'backend_unreachable', 'detail' => $err], 502);
        }
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $statusCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $respHeaders = substr($rawResponse, 0, $headerSize);
        $respBody = substr($rawResponse, $headerSize);
        curl_close($ch);

        http_response_code($statusCode);
        // Reenviamos solo Content-Type y Content-Disposition para que las
        // descargas (admin_download.php) lleguen al navegador correctamente.
        foreach (explode("\r\n", $respHeaders) as $line) {
            if (preg_match('/^(Content-Type|Content-Disposition|Cache-Control):/i', $line)) {
                header($line);
            }
        }
        // Si el backend falla (500), el navegador aún necesita CORS para que
        // el frontend pueda leer el JSON de error en lugar de un bloqueo opaco.
        Security::bootstrapCors();
        echo $respBody;
        exit;
    }

    private static function backendUrlFor(string $app): ?string
    {
        $envKey = strtoupper($app) . '_BACKEND_URL';
        $url = env($envKey);
        if (!$url) {
            $envFile = __DIR__ . '/../.env';
            if (is_file($envFile)) {
                foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                    if (preg_match('/^' . preg_quote($envKey, '/') . '\s*=\s*(.+)$/', trim($line), $m)) {
                        $url = trim($m[1]);
                        break;
                    }
                }
            }
        }
        // Defaults de desarrollo si .env no lo define.
        if (!$url) {
            $defaults = [
                'LIBRERIAGABI_BACKEND_URL' => 'http://localhost/libreria_gabi/backend/public',
            ];
            $url = $defaults[$envKey] ?? null;
        }
        return $url ?: null;
    }

    /**
     * Como verifyAdminAuth pero sin exigir admin: válida sesión OIDC y devuelve
     * la row de users. Para proxies a backends de apps donde la propia ruta
     * decide si necesita admin.
     */
    private static function verifyAuthenticated(): array
    {
        self::startSession();
        $session = self::activeAccount();
        if (!$session) Response::json(['error' => 'unauthenticated'], 401);
        if (time() >= ($session['expires_at'] ?? 0)) {
            $refreshed = self::tryRefreshSession((int)$_SESSION['oauth_active_user'], $session);
            if (!$refreshed) Response::json(['error' => 'session_expired'], 401);
            $session = $refreshed;
        }
        try {
            $resourceServer = ServerFactory::resourceServer();
            $psr17 = new Psr17Factory();
            $req = $psr17->createServerRequest('GET', '/oauth/userinfo')
                ->withHeader('Authorization', 'Bearer ' . $session['access_token']);
            $req = $resourceServer->validateAuthenticatedRequest($req);
            $userId = (int)$req->getAttribute('oauth_user_id');
        } catch (OAuthServerException $e) {
            Response::json(['error' => 'invalid_token'], 401);
        }
        $stmt = Database::connect()->prepare(
            'SELECT id, email, name, role FROM users WHERE id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();
        if (!$user) Response::json(['error' => 'user_not_found'], 401);
        return $user;
    }

    /**
     * Construye un PSR-7 ServerRequest "fake" con los parámetros del grant
     * y lo pasa al AuthorizationServer para que lo procese in-process.
     * Devuelve el JSON ya decodificado (access_token, id_token, etc.).
     */
    private static function exchangeCodeInternal(array $params): array
    {
        $psr17 = new Psr17Factory();
        $body = $psr17->createStream(http_build_query($params));
        $request = $psr17->createServerRequest('POST', '/oauth/token')
            ->withHeader('Content-Type', 'application/x-www-form-urlencoded')
            ->withParsedBody($params)
            ->withBody($body);

        $response = ServerFactory::authorizationServer()
            ->respondToAccessTokenRequest($request, $psr17->createResponse());

        $json = (string)$response->getBody();
        return json_decode($json, true) ?: [];
    }
}
