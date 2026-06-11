<?php

require_once __DIR__ . '/../oauth2/Entities/UserEntity.php';

use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use LibreriaGabi\OAuth2\Entities\UserEntity;
use LibreriaGabi\OAuth2\Repositories\UserRepository;
use LibreriaGabi\OAuth2\ServerFactory;

/**
 * Controlador OIDC. Implementa los endpoints del Identity Provider:
 *   - GET  /.well-known/openid-configuration  (discovery)
 *   - GET  /.well-known/jwks.json             (clave pública en formato JWK)
 *   - GET  /oauth/authorize                   (entrada del flujo)
 *   - POST /oauth/token                       (canjea code por tokens)
 *   - GET  /oauth/userinfo                    (claims del usuario)
 *   - GET/POST /idp/login                     (formulario propio del IdP)
 *   - POST /idp/logout                        (cierra sesión del IdP)
 *
 * El estado de "usuario logueado en el IdP" se mantiene en $_SESSION['idp_user_id'].
 * Esto es lo que permite Single Sign-On entre apps del ecosistema.
 */
class OAuthController
{
    /**
     * Nonce CSP por petición — permite emitir <script nonce="..."> en
     * páginas del IdP sin abrir 'unsafe-inline'. Lo setea idpHtmlHeaders()
     * y lo consume renderLoginForm (toggle del ojo en el campo de password).
     */
    private static string $cspNonce = '';

    private static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_set_cookie_params([
                'lifetime' => 0,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            session_name('idp_session');
            session_start();
        }
    }

    private static function buildPsr7Request()
    {
        $psr17 = new Psr17Factory();
        $creator = new ServerRequestCreator($psr17, $psr17, $psr17, $psr17);
        return $creator->fromGlobals();
    }

    private static function emit($response): void
    {
        http_response_code($response->getStatusCode());
        foreach ($response->getHeaders() as $name => $values) {
            foreach ($values as $value) header(sprintf('%s: %s', $name, $value), false);
        }
        echo $response->getBody();
        exit;
    }

    public static function discovery(): void
    {
        $issuer = ServerFactory::issuer();
        Response::json([
            'issuer' => $issuer,
            'authorization_endpoint' => $issuer . '/oauth/authorize',
            'token_endpoint' => $issuer . '/oauth/token',
            'userinfo_endpoint' => $issuer . '/oauth/userinfo',
            'jwks_uri' => $issuer . '/.well-known/jwks.json',
            'response_types_supported' => ['code'],
            'subject_types_supported' => ['public'],
            'id_token_signing_alg_values_supported' => ['RS256'],
            'token_endpoint_auth_methods_supported' => ['none', 'client_secret_post'],
            'scopes_supported' => ['openid', 'profile', 'email'],
            'grant_types_supported' => ['authorization_code', 'refresh_token'],
            'code_challenge_methods_supported' => ['S256'],
            'claims_supported' => ['sub', 'email', 'email_verified', 'name', 'given_name', 'family_name', 'preferred_username', 'role'],
        ]);
    }

    public static function jwks(): void
    {
        $publicKey = file_get_contents(ServerFactory::publicKeyPath());
        $resource = openssl_pkey_get_public($publicKey);
        $details = openssl_pkey_get_details($resource);
        Response::json([
            'keys' => [[
                'kty' => 'RSA',
                'use' => 'sig',
                'alg' => 'RS256',
                'kid' => 'oauth2-server-key',
                'n' => rtrim(strtr(base64_encode($details['rsa']['n']), '+/', '-_'), '='),
                'e' => rtrim(strtr(base64_encode($details['rsa']['e']), '+/', '-_'), '='),
            ]],
        ]);
    }

    public static function authorize(): void
    {
        self::startSession();
        $server = ServerFactory::authorizationServer();
        $request = self::buildPsr7Request();

        try {
            $authRequest = $server->validateAuthorizationRequest($request);

            // OIDC `prompt=login`: fuerza form aunque haya sesión activa.
            // Lo usa "Añadir cuenta" para que el usuario pueda autenticarse
            // como otro usuario sin SSO automático con la cuenta activa.
            $promptList = preg_split('/\s+/', trim((string)($_GET['prompt'] ?? '')));
            $forceLogin = in_array('login', $promptList, true);

            $activeUser = (int)($_SESSION['idp_active_user'] ?? 0);

            // Enforcement de revocación: si la sesión multi-device fue revocada
            // (desde "Sesiones" del settings), invalidamos la entrada en
            // idp_users y forzamos relogin para ese usuario.
            if ($activeUser > 0) {
                $sessionDbId = (string)($_SESSION['session_db_ids'][$activeUser] ?? '');
                if ($sessionDbId !== '') {
                    $check = Database::connect()->prepare(
                        'SELECT id FROM user_sessions
                         WHERE id = :id AND user_id = :uid AND revoked_at IS NULL
                         LIMIT 1'
                    );
                    $check->execute([':id' => $sessionDbId, ':uid' => $activeUser]);
                    if (!$check->fetch()) {
                        // Sesión revocada: quitamos el user de idp_users y session_db_ids.
                        if (isset($_SESSION['idp_users']) && is_array($_SESSION['idp_users'])) {
                            $_SESSION['idp_users'] = array_values(array_filter(
                                $_SESSION['idp_users'],
                                static fn($id): bool => (int)$id !== $activeUser
                            ));
                        }
                        unset($_SESSION['session_db_ids'][$activeUser]);
                        $_SESSION['idp_active_user'] = $_SESSION['idp_users'][0] ?? null;
                        $activeUser = (int)($_SESSION['idp_active_user'] ?? 0);
                    }
                }
            }

            if ($forceLogin || $activeUser <= 0) {
                $_SESSION['pending_auth_request'] = serialize($authRequest);
                // Quitamos `prompt` del returnTo para que tras el login no se
                // vuelva a forzar el form en bucle.
                $returnQuery = $_GET;
                unset($returnQuery['prompt']);
                $returnTo = '/oauth/authorize?' . http_build_query($returnQuery);
                header('Location: /idp/login?return=' . urlencode($returnTo));
                exit;
            }

            // Refrescamos last_seen de la sesión para que el listado lo muestre.
            $sessionDbId = (string)($_SESSION['session_db_ids'][$activeUser] ?? '');
            if ($sessionDbId !== '') {
                $upd = Database::connect()->prepare(
                    'UPDATE user_sessions SET last_seen = NOW() WHERE id = :id'
                );
                $upd->execute([':id' => $sessionDbId]);
            }

            $user = new UserEntity();
            $user->setIdentifier((string)$activeUser);
            $authRequest->setUser($user);
            // Aprobamos automáticamente todos los scopes (consent implícito).
            $authRequest->setAuthorizationApproved(true);

            $psr17 = new Psr17Factory();
            $response = $server->completeAuthorizationRequest($authRequest, $psr17->createResponse());
            self::emit($response);
        } catch (OAuthServerException $e) {
            $psr17 = new Psr17Factory();
            self::emit($e->generateHttpResponse($psr17->createResponse()));
        } catch (\Throwable $e) {
            Response::json(['error' => 'authorize_error', 'message' => $e->getMessage()], 500);
        }
    }

    public static function token(): void
    {
        $server = ServerFactory::authorizationServer();
        $request = self::buildPsr7Request();
        try {
            $psr17 = new Psr17Factory();
            $response = $server->respondToAccessTokenRequest($request, $psr17->createResponse());
            self::emit($response);
        } catch (OAuthServerException $e) {
            $psr17 = new Psr17Factory();
            self::emit($e->generateHttpResponse($psr17->createResponse()));
        } catch (\Throwable $e) {
            Response::json(['error' => 'token_error', 'message' => $e->getMessage()], 500);
        }
    }

    public static function userinfo(): void
    {
        $server = ServerFactory::resourceServer();
        $request = self::buildPsr7Request();
        try {
            $request = $server->validateAuthenticatedRequest($request);
            $userId = (int)$request->getAttribute('oauth_user_id');
            $user = UserRepository::findById($userId);
            if (!$user) {
                Response::json(['error' => 'user_not_found'], 404);
            }
            Response::json($user->claims);
        } catch (OAuthServerException $e) {
            $psr17 = new Psr17Factory();
            self::emit($e->generateHttpResponse($psr17->createResponse()));
        }
    }

    public static function idpLogin(): void
    {
        self::startSession();
        $returnTo = $_GET['return'] ?? $_POST['return'] ?? '/';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // El campo `identifier` acepta email O username. Detectamos cuál es
            // por su forma (presencia de @) y elegimos la columna de búsqueda.
            // Mantenemos compat con clientes legacy que aún envían `email`.
            $identifier = trim((string)($_POST['identifier'] ?? $_POST['email'] ?? ''));
            $password = (string)($_POST['password'] ?? '');

            $isEmail = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;
            $column = $isEmail ? 'email' : 'username';
            $stmt = Database::connect()->prepare(
                "SELECT id, email, name, password, banned_at, is_email_verified
                   FROM users WHERE {$column} = :v LIMIT 1"
            );
            $stmt->execute([':v' => $isEmail ? strtolower($identifier) : $identifier]);
            $user = $stmt->fetch();

            if (!$user || $user['banned_at'] !== null || !password_verify($password, $user['password'])) {
                self::renderLoginForm($returnTo, 'Credenciales inválidas o cuenta deshabilitada.');
                return;
            }
            if (!$user['is_email_verified']) {
                self::renderLoginForm($returnTo, 'Verifica tu email antes de iniciar sesión.');
                return;
            }

            // Multi-cuenta: añadimos al array de usuarios autenticados y
            // marcamos como activo. Si ya estaba, simplemente promocionamos.
            $newUserId = (int)$user['id'];
            if (!isset($_SESSION['idp_users']) || !is_array($_SESSION['idp_users'])) {
                $_SESSION['idp_users'] = [];
            }
            if (!in_array($newUserId, $_SESSION['idp_users'], true)) {
                $_SESSION['idp_users'][] = $newUserId;
            }
            $_SESSION['idp_active_user'] = $newUserId;
            session_regenerate_id(true);

            // Multi-device tracking: registramos la sesión con IP, país y UA.
            // Si la combinación dispositivo+país es nueva, dispara email alert.
            self::recordSession($newUserId, (string)$user['email'], (string)$user['name']);

            DeferredTasks::redirect($returnTo);
        }

        self::renderLoginForm($returnTo, '');
    }

    /**
     * Parsea un User-Agent y devuelve una etiqueta amigable estilo
     * "Chrome en Windows", "Safari en macOS", "Firefox en Linux".
     */
    private static function parseDevice(string $userAgent): string
    {
        $ua = $userAgent;
        // Detectar OS
        $os = match (true) {
            str_contains($ua, 'Windows NT') => 'Windows',
            str_contains($ua, 'Macintosh') || str_contains($ua, 'Mac OS X') => 'macOS',
            str_contains($ua, 'Android') => 'Android',
            str_contains($ua, 'iPhone') || str_contains($ua, 'iPad') => 'iOS',
            str_contains($ua, 'Linux') => 'Linux',
            default => 'Desconocido',
        };
        // Detectar navegador (orden importa: Edge contiene "Chrome", etc.)
        $browser = match (true) {
            str_contains($ua, 'Edg/') => 'Edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'Opera') => 'Opera',
            str_contains($ua, 'Firefox/') => 'Firefox',
            str_contains($ua, 'Chrome/') => 'Chrome',
            str_contains($ua, 'Safari/') => 'Safari',
            default => 'Navegador',
        };
        return "{$browser} en {$os}";
    }

    /**
     * Crea una nueva fila en user_sessions para el login que acaba de ocurrir.
     * Resuelve IP a país, parsea UA a etiqueta de dispositivo, y guarda el
     * session_id en $_SESSION['session_db_id'] para futuros checks. Si detecta
     * que es un dispositivo/ubicación nueva (no había sesión previa con esa
     * combinación), envía alerta de seguridad al email del usuario.
     */
    private static function recordSession(int $userId, string $userEmail, string $userName): void
    {
        $ip = Security::getClientIp();
        $ua = (string)($_SERVER['HTTP_USER_AGENT'] ?? '');
        $device = self::parseDevice($ua);
        $geo = GeoLocationService::lookup($ip);
        $countryCode = $geo['code'] ?? null;
        $countryName = $geo['name'] ?? null;

        // Fingerprint para detectar "nuevo dispositivo/ubicación":
        // Comparamos contra sesiones previas no-revocadas con el mismo
        // (device_label, country_code) — si no existe, es nueva combinación.
        $db = Database::connect();
        $check = $db->prepare(
            'SELECT id FROM user_sessions
             WHERE user_id = :uid AND device_label = :device
               AND (country_code <=> :cc) AND revoked_at IS NULL
             LIMIT 1'
        );
        $check->execute([':uid' => $userId, ':device' => $device, ':cc' => $countryCode]);
        $isNewDevice = !$check->fetch();

        // Generamos session_id propio y lo guardamos en la sesión PHP.
        $sessionId = hash('sha256', random_bytes(32));
        $now = date('Y-m-d H:i:s');
        $ins = $db->prepare(
            'INSERT INTO user_sessions (id, user_id, ip, country_code, country_name, user_agent, device_label, last_seen)
             VALUES (:id, :uid, :ip, :cc, :cn, :ua, :dev, :seen)'
        );
        $ins->execute([
            ':id' => $sessionId, ':uid' => $userId, ':ip' => $ip,
            ':cc' => $countryCode, ':cn' => $countryName,
            ':ua' => mb_substr($ua, 0, 512), ':dev' => $device, ':seen' => $now,
        ]);

        if (!isset($_SESSION['session_db_ids']) || !is_array($_SESSION['session_db_ids'])) {
            $_SESSION['session_db_ids'] = [];
        }
        $_SESSION['session_db_ids'][$userId] = $sessionId;

        // Alerta por email si es nuevo dispositivo/ubicación.
        if ($isNewDevice && MailService::isConfigured()) {
            $location = $countryName ? $countryName . ' (IP ' . $ip . ')' : 'IP ' . $ip;

            // Generamos token one-shot para que los botones Sí/No del email
            // puedan autorizar la acción sin sesión previa. Vive 24h.
            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);
            $expiresAt = date('Y-m-d H:i:s', time() + 86400);
            $alertIns = $db->prepare(
                'INSERT INTO login_alerts (user_id, user_session_id, token_hash, expires_at)
                 VALUES (:uid, :sid, :hash, :exp)'
            );
            $alertIns->execute([
                ':uid' => $userId, ':sid' => $sessionId,
                ':hash' => $tokenHash, ':exp' => $expiresAt,
            ]);
            $confirmUrl = ServerFactory::issuer() . '/idp/login-confirm?token=' . $rawToken . '&action=ok';
            $denyUrl = ServerFactory::issuer() . '/idp/login-confirm?token=' . $rawToken . '&action=deny';

            DeferredTasks::runAfterResponse(static function () use (
                $userEmail, $userName, $device, $location, $confirmUrl, $denyUrl
            ): void {
                MailService::sendNewDeviceAlert(
                    $userEmail,
                    $userName,
                    $device,
                    $location,
                    date('d/m/Y H:i'),
                    $confirmUrl,
                    $denyUrl
                );
            });
        }
    }

    /**
     * Confirma o niega un nuevo inicio de sesión via el botón del email.
     *   action=ok:   solo marca el token como usado (acuse de recibo).
     *   action=deny: revoca la user_session sospechosa + genera password reset
     *                token + redirige al usuario a /idp/reset-password.
     */
    public static function idpLoginConfirm(): void
    {
        $token = (string)($_GET['token'] ?? '');
        $action = (string)($_GET['action'] ?? '');
        if ($token === '' || !ctype_xdigit($token) || strlen($token) !== 64
            || !in_array($action, ['ok', 'deny'], true)) {
            self::renderVerifyResult(false, 'Enlace inválido o caducado.');
            return;
        }

        $tokenHash = hash('sha256', $token);
        $db = Database::connect();
        $stmt = $db->prepare(
            'SELECT id, user_id, user_session_id FROM login_alerts
             WHERE token_hash = :hash AND used_at IS NULL AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([':hash' => $tokenHash]);
        $alert = $stmt->fetch();
        if (!$alert) {
            self::renderVerifyResult(false, 'Enlace inválido o caducado.');
            return;
        }

        $mark = $db->prepare(
            'UPDATE login_alerts SET used_at = NOW(), action = :action WHERE id = :id'
        );
        $mark->execute([':action' => $action, ':id' => $alert['id']]);

        if ($action === 'ok') {
            self::renderVerifyResult(true, 'Confirmado. Gracias, ningún cambio necesario.');
            return;
        }

        // action === 'deny': revocamos la sesión sospechosa y generamos
        // un password reset token + redirigimos.
        $revoke = $db->prepare(
            'UPDATE user_sessions SET revoked_at = NOW()
             WHERE id = :id AND revoked_at IS NULL'
        );
        $revoke->execute([':id' => $alert['user_session_id']]);

        $rawReset = bin2hex(random_bytes(32));
        $resetHash = hash('sha256', $rawReset);
        $resetExp = date('Y-m-d H:i:s', time() + 3600);
        $cleanReset = $db->prepare('DELETE FROM password_reset_tokens WHERE user_id = :uid');
        $cleanReset->execute([':uid' => $alert['user_id']]);
        $insReset = $db->prepare(
            'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
             VALUES (:uid, :hash, :exp)'
        );
        $insReset->execute([':uid' => $alert['user_id'], ':hash' => $resetHash, ':exp' => $resetExp]);

        header('Location: /idp/reset-password?token=' . $rawReset);
        exit;
    }

    /**
     * Si la sesión multi-device del usuario activo (idp_active_user) está
     * revocada en user_sessions, expulsa esa cuenta de idp_users y promociona
     * otra (o vacía la sesión IdP entera si no quedan).
     *
     * Llamar al inicio de cualquier endpoint del IdP que requiera sesión
     * activa (settings, switch-account silencioso, etc.).
     */
    private static function enforceSessionGuard(): void
    {
        $activeId = (int)($_SESSION['idp_active_user'] ?? 0);
        if ($activeId <= 0) return;

        $sessionDbId = (string)($_SESSION['session_db_ids'][$activeId] ?? '');
        if ($sessionDbId === '') return;

        $check = Database::connect()->prepare(
            'SELECT id FROM user_sessions
             WHERE id = :id AND user_id = :uid AND revoked_at IS NULL
             LIMIT 1'
        );
        $check->execute([':id' => $sessionDbId, ':uid' => $activeId]);
        if ($check->fetch()) return; // sesión OK

        // Sesión revocada — kick.
        if (isset($_SESSION['idp_users']) && is_array($_SESSION['idp_users'])) {
            $_SESSION['idp_users'] = array_values(array_filter(
                $_SESSION['idp_users'],
                static fn($id): bool => (int)$id !== $activeId
            ));
        }
        unset($_SESSION['session_db_ids'][$activeId]);
        $_SESSION['idp_active_user'] = $_SESSION['idp_users'][0] ?? null;
    }

    /**
     * Sanitiza el `return` del IdP: solo aceptamos paths relativos que arrancan
     * con "/" (y no "//" para evitar protocolo-relativo cross-origin). Bloquea
     * el vector clásico de open redirect ?return=https://evil.com.
     */
    private static function safeReturn(?string $candidate): string
    {
        $candidate = (string)$candidate;
        if ($candidate === '' || !str_starts_with($candidate, '/') || str_starts_with($candidate, '//')) {
            return '/';
        }
        return $candidate;
    }

    /**
     * Headers comunes para las páginas HTML del IdP (login, register).
     * - CSP relajada solo aquí: estilos inline + favicon/símbolo desde /assets.
     *   form-action se omite porque Chrome lo aplica a toda la cadena de
     *   redirects y nuestra cadena acaba en otro origen.
     * - Referrer-Policy: same-origin (no no-referrer) para que el browser mande
     *   Origin: <self> en el POST del form y la salvaguarda CORS no lo rechace.
     */
    private static function idpHtmlHeaders(): void
    {
        // Nonce por petición — permite scripts inline emitidos por el server
        // sin abrir CSP a 'unsafe-inline'. Cualquier <script> sin el nonce
        // queda bloqueado.
        self::$cspNonce = bin2hex(random_bytes(16));
        $nonce = self::$cspNonce;
        header('Content-Type: text/html; charset=utf-8');
        header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'; script-src 'nonce-{$nonce}'; img-src 'self'; media-src 'self'; frame-ancestors 'none'; base-uri 'none'", true);
        header('Referrer-Policy: same-origin', true);
    }

    /**
     * Hoja de estilos común a login y register (mismo look & feel).
     */
    private static function idpStyles(): string
    {
        return <<<CSS
<style>
  :root {
    --navy: #1a2d4e;
    --navy-soft: #243a63;
    --gold: #c9a84c;
    --cream: #faf8f3;
    --paper: #ffffff;
    --border: #e4e6ec;
    --muted: #5a6478;
    --error: #c0392b;
  }
  *, *::before, *::after { box-sizing: border-box; }
  html, body { height: 100%; }
  body {
    margin: 0;
    font-family: 'Inter', system-ui, -apple-system, "Segoe UI", sans-serif;
    background:
      radial-gradient(1200px 600px at 80% -10%, rgba(201,168,76,0.12), transparent 60%),
      radial-gradient(900px 600px at -10% 110%, rgba(26,45,78,0.08), transparent 60%),
      var(--cream);
    color: var(--navy);
    display: grid;
    place-items: center;
    padding: 1.5rem;
  }
  .card {
    width: 100%;
    max-width: 420px;
    background: rgba(255, 255, 255, 0.78);
    backdrop-filter: blur(14px);
    -webkit-backdrop-filter: blur(14px);
    border: 1px solid rgba(255, 255, 255, 0.45);
    border-radius: 16px;
    padding: 2.25rem 2rem 2rem;
    box-shadow: 0 24px 60px rgba(17, 30, 51, 0.15);
  }
  .brand {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    margin-bottom: 1.5rem;
  }
  .brand img { width: 38px; height: 38px; object-fit: contain; }
  .brand-text { display: flex; flex-direction: column; line-height: 1.15; }
  .brand-name {
    font-weight: 700; font-size: 1.05rem; color: var(--navy); letter-spacing: -0.01em;
  }
  .brand-tagline {
    font-size: 0.62rem; text-transform: uppercase; letter-spacing: 0.16em; color: var(--muted);
  }
  h1 { margin: 0 0 0.25rem; font-size: 1.35rem; font-weight: 700; color: var(--navy); }
  .lead { margin: 0 0 1.5rem; font-size: 0.88rem; color: var(--muted); }
  form { display: grid; gap: 0.95rem; }
  label {
    display: grid; gap: 0.35rem; font-size: 0.8rem; font-weight: 600;
    color: var(--navy); letter-spacing: 0.01em;
  }
  /* Sufijo "(opcional)" dentro de un label: fluye inline al lado del texto principal,
     más suave y ligero, sin romper la línea. */
  .optional {
    font-weight: 400; font-size: 0.9em; color: var(--muted);
    margin-left: 0.25rem; letter-spacing: 0;
  }
  input[type="email"],
  input[type="password"],
  input[type="text"],
  input[type="tel"] {
    padding: 0.7rem 0.85rem;
    background: var(--cream);
    border: 1px solid var(--border);
    border-radius: 10px;
    font-size: 0.92rem;
    color: var(--navy);
    font-family: inherit;
    transition: border-color 0.18s, background 0.18s, box-shadow 0.18s;
  }
  input::placeholder { color: #a3acbf; }
  input:hover { background: #fff; }
  input:focus {
    outline: none; background: #fff;
    border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.18);
  }
  button[type="submit"] {
    margin-top: 0.5rem;
    padding: 0.78rem 1rem;
    background: var(--navy);
    color: #fff;
    border: 0;
    border-radius: 10px;
    font-weight: 600; font-size: 0.95rem;
    cursor: pointer; font-family: inherit;
    transition: background 0.18s, transform 0.1s;
    letter-spacing: 0.02em;
  }
  button[type="submit"]:hover { background: var(--navy-soft); }
  button[type="submit"]:active { transform: translateY(1px); }
  .err {
    margin: 0.5rem 0 0;
    padding: 0.65rem 0.8rem;
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: var(--error);
    border-radius: 8px;
    font-size: 0.83rem;
  }
  .alt-action {
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px dashed var(--border);
    text-align: center;
    font-size: 0.85rem;
    color: var(--muted);
  }
  .alt-action a {
    color: var(--navy); font-weight: 600; text-decoration: none;
    border-bottom: 1px solid var(--gold);
    padding-bottom: 1px;
    transition: color 0.18s;
  }
  .alt-action a:hover { color: var(--gold); }
  footer {
    margin-top: 1.5rem;
    text-align: center;
    font-size: 0.72rem;
    color: var(--muted);
    letter-spacing: 0.04em;
  }
  footer .gold { color: var(--gold); font-weight: 600; }
  .hint {
    margin: 0.2rem 0 0;
    font-size: 0.72rem;
    color: var(--muted);
  }
  .video-container {
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    z-index: -1;
    overflow: hidden;
  }
  .bg-video {
    width: 100%;
    height: 100%;
    object-fit: cover;
  }
</style>
CSS;
    }

    /**
     * Cabecera de marca común (logo Librería Gabi + tagline IdP). Idéntico para
     * login y registro.
     */
    private static function idpBrandHeader(): string
    {
        return <<<HTML
<div class="brand">
  <img src="/assets/logo.png" alt="Librería Gabi" width="38" height="38">
  <div class="brand-text">
    <span class="brand-name">Librería Gabi</span>
    <span class="brand-tagline">Identity Provider</span>
  </div>
</div>
HTML;
    }

    private static function renderLoginForm(string $returnTo, string $error): void
    {
        $returnEsc = htmlspecialchars($returnTo, ENT_QUOTES, 'UTF-8');
        $errorBlock = $error !== '' ? '<p class="err">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>' : '';
        $registerHref = '/idp/register?return=' . urlencode($returnTo);
        self::idpHtmlHeaders();
        $styles = self::idpStyles();
        $brand = self::idpBrandHeader();
        $nonce = self::$cspNonce;
        $eyeStyles = self::passwordEyeStyles();
        $eyeScript = self::passwordEyeScript();
        echo <<<HTML
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Iniciar sesión · Librería Gabi</title>
<link rel="icon" type="image/png" href="/assets/favicon.png">
{$styles}
{$eyeStyles}
</head>
<body>
<div class="video-container">
  <video autoplay loop muted playsinline class="bg-video">
    <source src="/assets/video-fondo-login.mp4" type="video/mp4">
  </video>
</div>
<div class="card">
  {$brand}
  <h1>Iniciar sesión</h1>
  <p class="lead">Accede a tu cuenta de Librería Gabi.</p>
  <form method="post" action="/idp/login">
    <input type="hidden" name="return" value="{$returnEsc}">
    <label for="identifier">Email o usuario
      <input id="identifier" name="identifier" type="text" required autofocus autocomplete="username" placeholder="tu@email.com o tu usuario">
    </label>
    <label for="password">Contraseña
      <div class="pw-wrap" data-reveal="false">
        <input id="password" name="password" type="password" required autocomplete="current-password" placeholder="••••••••" class="pw-input">
        <button type="button" class="pw-eye" tabindex="-1" aria-label="Mostrar u ocultar la contraseña" aria-pressed="false">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/>
            <circle cx="12" cy="12" r="3"/>
            <line class="pw-eye-strike" x1="4" y1="4" x2="20" y2="20"/>
          </svg>
        </button>
      </div>
    </label>
    <button type="submit">Entrar</button>
    <p style="margin:.4rem 0 0;text-align:right;font-size:.78rem;">
      <a href="/idp/forgot-password" style="color:#5a6478;text-decoration:none;border-bottom:1px dotted #c2c8d4;padding-bottom:1px;">¿Olvidaste tu contraseña?</a>
    </p>
    {$errorBlock}
  </form>
  <p class="alt-action">¿No tienes cuenta? <a href="{$registerHref}">Crear una</a></p>
</div>
<script nonce="{$nonce}">{$eyeScript}</script>
</body>
</html>
HTML;
        exit;
    }

    /**
     * CSS del botón "ojo" para mostrar/ocultar la contraseña. Replica solo
     * el botón del componente dimi.me/lab/password-input — sin overlay de
     * caracteres ni flip 3D del texto. El <input> conserva su look estándar
     * del IdP, solo dejándole padding-right para hacer hueco al botón.
     *
     * Animaciones:
     *   - Hover: scale(1.1) con spring suave.
     *   - Active: scale(0.92).
     *   - Strike-line del SVG: stroke-dashoffset se anima cuando reveal=true
     *     (línea tachada "dibujándose" sobre el ojo).
     */
    private static function passwordEyeStyles(): string
    {
        return <<<CSS
<style>
  .pw-wrap { position: relative; display: block; }
  .pw-wrap .pw-input { width: 100%; padding-right: 2.6rem; }
  .pw-eye {
    position: absolute;
    right: 0.35rem;
    top: 50%;
    display: grid; place-items: center;
    width: 1.9rem; height: 1.9rem;
    background: transparent;
    border: 0;
    border-radius: 8px;
    color: var(--muted);
    cursor: pointer;
    transform: translateY(-50%);
    transition: color 0.18s, background 0.18s, transform 0.15s cubic-bezier(0.34, 1.56, 0.64, 1);
  }
  .pw-eye:hover {
    color: var(--navy);
    background: rgba(26, 45, 78, 0.06);
    transform: translateY(-50%) scale(1.1);
  }
  .pw-eye:active { transform: translateY(-50%) scale(0.92); }
  .pw-eye:focus-visible {
    outline: 2px solid var(--gold);
    outline-offset: 2px;
  }
  .pw-eye-strike {
    stroke-dasharray: 24;
    stroke-dashoffset: 24;
    transition: stroke-dashoffset 0.32s cubic-bezier(0.4, 0, 0.2, 1);
  }
  .pw-wrap[data-reveal="true"] .pw-eye-strike { stroke-dashoffset: 0; }
  /* Edge añade su propio ícono de revelar contraseña; lo quitamos para no
     duplicarlo con el nuestro. */
  .pw-wrap .pw-input::-ms-reveal { display: none; }
</style>
CSS;
    }

    /**
     * JS mínimo del botón ojo: toggle entre type="password" y type="text".
     * Necesita nonce CSP porque la CSP del IdP es default-src 'none'.
     */
    private static function passwordEyeScript(): string
    {
        return <<<'JS'
(function () {
  // Soporta múltiples .pw-wrap en la misma página (login = 1; registro = 2;
  // settings/reset = N). Cada wrap gestiona su propio input + botón ojo.
  var wraps = document.querySelectorAll('.pw-wrap');
  Array.prototype.forEach.call(wraps, function (wrap) {
    var input = wrap.querySelector('.pw-input');
    var eye = wrap.querySelector('.pw-eye');
    if (!input || !eye) return;
    eye.addEventListener('click', function () {
      var revealed = wrap.getAttribute('data-reveal') === 'true';
      var next = !revealed;
      wrap.setAttribute('data-reveal', next ? 'true' : 'false');
      input.type = next ? 'text' : 'password';
      eye.setAttribute('aria-pressed', next ? 'true' : 'false');
      // Devuelve el foco al input para que el usuario siga escribiendo sin
      // tener que volver a clicar dentro.
      input.focus();
    });
  });
})();
JS;
    }

    /**
     * Maneja registro: GET muestra el formulario, POST crea el usuario y lo
     * loguea inmediatamente (auto-verificado en Pruebas).
     *
     * TODO prod: gating con verificación de email + MailService.
     */
    /**
     * Maneja registro: GET muestra el formulario, POST guarda los datos en
     * pending_registrations y envía email de verificación. El usuario NO se
     * inserta en `users` hasta que confirma el email. Auto-login al confirmar.
     */
    public static function idpRegister(): void
    {
        self::startSession();
        $returnTo = self::safeReturn($_GET['return'] ?? $_POST['return'] ?? '/');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            self::renderRegisterForm($returnTo, '', ['name' => '', 'email' => '', 'username' => '']);
            return;
        }

        $name = trim((string)($_POST['name'] ?? ''));
        $username = trim((string)($_POST['username'] ?? ''));
        $email = trim(strtolower((string)($_POST['email'] ?? '')));
        $phone = trim((string)($_POST['phone'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $passwordConfirm = (string)($_POST['password_confirm'] ?? '');
        $values = ['name' => $name, 'email' => $email, 'username' => $username, 'phone' => $phone];

        if ($name === '') {
            self::renderRegisterForm($returnTo, 'El nombre es obligatorio.', $values);
            return;
        }
        if ($username === '' || !preg_match('/^[a-zA-Z0-9_.-]{3,32}$/', $username)) {
            self::renderRegisterForm($returnTo, 'Usuario: 3-32 caracteres, letras/números/punto/guion.', $values);
            return;
        }
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            self::renderRegisterForm($returnTo, 'Email no válido.', $values);
            return;
        }
        if (strlen($password) < 8) {
            self::renderRegisterForm($returnTo, 'La contraseña debe tener al menos 8 caracteres.', $values);
            return;
        }
        if ($password !== $passwordConfirm) {
            self::renderRegisterForm($returnTo, 'Las contraseñas no coinciden.', $values);
            return;
        }
        // Phone es opcional. Si viene relleno, validamos formato laxo (dígitos +
        // separadores comunes, 5-30 chars). Si solo viene espacios o caracteres
        // raros, lo rechazamos para no guardar basura.
        if ($phone === '') {
            self::renderRegisterForm($returnTo, 'El teléfono es obligatorio.', $values);
            return;
        }
        if (!preg_match('/^[\d\s+\-()]{5,30}$/', $phone)) {
            self::renderRegisterForm($returnTo, 'Formato de teléfono no válido (5-30 caracteres, solo dígitos y + - ( )).', $values);
            return;
        }

        $db = Database::connect();
        // Unicidad del email, username y teléfono contra users (cuentas confirmadas).
        $existsEmail = $db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $existsEmail->execute([':email' => $email]);
        if ($existsEmail->fetch()) {
            self::renderRegisterForm($returnTo, 'Ya existe una cuenta con ese email.', $values);
            return;
        }
        $existsUsername = $db->prepare('SELECT id FROM users WHERE username = :u LIMIT 1');
        $existsUsername->execute([':u' => $username]);
        if ($existsUsername->fetch()) {
            self::renderRegisterForm($returnTo, 'Ese nombre de usuario ya está en uso.', $values);
            return;
        }
        $existsPhone = $db->prepare('SELECT id FROM users WHERE phone = :phone LIMIT 1');
        $existsPhone->execute([':phone' => $phone]);
        if ($existsPhone->fetch()) {
            self::renderRegisterForm($returnTo, 'Ya existe una cuenta con ese número de teléfono.', $values);
            return;
        }
        // Y contra pendings ACTIVOS (no caducados) para evitar reservar el
        // nombre indefinidamente — los caducados los machacamos abajo en UPDATE.
        $existsPendingU = $db->prepare(
            'SELECT id FROM pending_registrations WHERE username = :u AND email <> :email AND used_at IS NULL AND expires_at > NOW() LIMIT 1'
        );
        $existsPendingU->execute([':u' => $username, ':email' => $email]);
        if ($existsPendingU->fetch()) {
            self::renderRegisterForm($returnTo, 'Ese nombre de usuario está pendiente de confirmación en otro registro.', $values);
            return;
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $parts = explode(' ', $name, 2);
        $firstName = $parts[0];
        $lastName = $parts[1] ?? '';

        // Token raw para el email; hash SHA-256 para BD.
        $rawToken = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', time() + 86400); // 24 horas

        // Si ya hay un pending previo con este email, lo refrescamos (mismo
        // usuario reintenta o cambia password). UPDATE en vez de INSERT.
        $pending = $db->prepare('SELECT id FROM pending_registrations WHERE email = :email LIMIT 1');
        $pending->execute([':email' => $email]);
        $pendingRow = $pending->fetch();

        $phoneValue = $phone !== '' ? $phone : null;

        if ($pendingRow) {
            $upd = $db->prepare(
                'UPDATE pending_registrations
                 SET username = :u, password_hash = :pass, name = :name, first_name = :first, last_name = :last,
                     phone = :phone, token_hash = :hash, return_to = :ret, expires_at = :exp, used_at = NULL
                 WHERE id = :id'
            );
            $upd->execute([
                ':u' => $username,
                ':pass' => $hash, ':name' => $name, ':first' => $firstName, ':last' => $lastName,
                ':phone' => $phoneValue,
                ':hash' => $tokenHash, ':ret' => $returnTo, ':exp' => $expiresAt, ':id' => $pendingRow['id'],
            ]);
        } else {
            $ins = $db->prepare(
                'INSERT INTO pending_registrations
                   (username, email, password_hash, name, first_name, last_name, phone, token_hash, return_to, expires_at)
                 VALUES (:u, :email, :pass, :name, :first, :last, :phone, :hash, :ret, :exp)'
            );
            $ins->execute([
                ':u' => $username,
                ':email' => $email, ':pass' => $hash, ':name' => $name,
                ':first' => $firstName, ':last' => $lastName, ':phone' => $phoneValue,
                ':hash' => $tokenHash, ':ret' => $returnTo, ':exp' => $expiresAt,
            ]);
        }

        $verifyUrl = ServerFactory::issuer() . '/idp/verify-email?token=' . $rawToken;

        // Si SMTP configurado → enviar y NO mostrar link. Si no → fallback dev.
        if (MailService::isConfigured()) {
            $sent = MailService::sendVerifyEmail($email, $verifyUrl, $name);
            if ($sent) {
                self::renderRegisterCheckInbox($email, '');
            } else {
                self::renderRegisterCheckInbox($email, $verifyUrl); // muestra link como fallback
            }
            return;
        }
        // Modo dev: link visible en pantalla.
        self::renderRegisterCheckInbox($email, $verifyUrl);
    }

    /**
     * El usuario llega aquí desde el link del email. Validamos el token,
     * trasvasamos pending_registrations → users, marcamos el token usado,
     * y auto-logueamos.
     */
    public static function idpVerifyEmail(): void
    {
        self::startSession();
        $token = (string)($_GET['token'] ?? '');

        if ($token === '' || !ctype_xdigit($token) || strlen($token) !== 64) {
            self::renderVerifyResult(false, 'Enlace inválido o caducado.');
            return;
        }

        $tokenHash = hash('sha256', $token);
        $db = Database::connect();
        $stmt = $db->prepare(
            'SELECT id, username, email, password_hash, name, first_name, last_name, phone, return_to
             FROM pending_registrations
             WHERE token_hash = :hash AND used_at IS NULL AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([':hash' => $tokenHash]);
        $pending = $stmt->fetch();
        if (!$pending) {
            self::renderVerifyResult(false, 'Enlace inválido o caducado.');
            return;
        }

        // Por si el usuario ya se creó por otro flujo entre que llegó el
        // email y el clic, evitamos duplicar.
        $exists = $db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
        $exists->execute([':email' => $pending['email']]);
        if ($exists->fetch()) {
            $del = $db->prepare('DELETE FROM pending_registrations WHERE id = :id');
            $del->execute([':id' => $pending['id']]);
            self::renderVerifyResult(false, 'Ya existe una cuenta con ese email. Inicia sesión normalmente.');
            return;
        }

        // Double-check unicidad de username AHORA (alguien podría haberlo
        // reclamado entre que llegó el email del usuario y el clic).
        if (!empty($pending['username'])) {
            $u = $db->prepare('SELECT id FROM users WHERE username = :u LIMIT 1');
            $u->execute([':u' => $pending['username']]);
            if ($u->fetch()) {
                self::renderVerifyResult(false, 'Ese nombre de usuario ya está en uso. Vuelve a registrarte con otro.');
                $del = $db->prepare('DELETE FROM pending_registrations WHERE id = :id');
                $del->execute([':id' => $pending['id']]);
                return;
            }
        }

        $db->beginTransaction();
        try {
            $insert = $db->prepare(
                'INSERT INTO users (username, email, password, password_hash, name, first_name, last_name, phone, role, is_email_verified, email_verified_at, created_at, is_active)
                 VALUES (:u, :email, :pass, :pass_hash, :name, :first, :last, :phone, \'user\', 1, NOW(), NOW(), 1)'
            );
            $insert->execute([
                ':u' => $pending['username'] ?: null,
                ':email' => $pending['email'],
                ':pass' => $pending['password_hash'],
                ':pass_hash' => $pending['password_hash'],
                ':name' => $pending['name'],
                ':first' => $pending['first_name'],
                ':last' => $pending['last_name'],
                ':phone' => $pending['phone'] ?? null,
            ]);
            $newUserId = (int)$db->lastInsertId();

            $mark = $db->prepare('UPDATE pending_registrations SET used_at = NOW() WHERE id = :id');
            $mark->execute([':id' => $pending['id']]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            self::renderVerifyResult(false, 'No se pudo activar la cuenta. Detalles: ' . $e->getMessage());
            return;
        }

        // Auto-login: añadir al array idp_users y marcar como activo.
        if (!isset($_SESSION['idp_users']) || !is_array($_SESSION['idp_users'])) {
            $_SESSION['idp_users'] = [];
        }
        if (!in_array($newUserId, $_SESSION['idp_users'], true)) {
            $_SESSION['idp_users'][] = $newUserId;
        }
        $_SESSION['idp_active_user'] = $newUserId;
        session_regenerate_id(true);

        // Track de sesión también en verify-email auto-login.
        self::recordSession($newUserId, (string)$pending['email'], (string)$pending['name']);

        // E2: auto-login completo. Si el registro original llevaba un `return`
        // que apunta a un OAuth flow pendiente, redirigimos ahí y el OAuth
        // completa SSO (con idp_active_user recién puesto). El usuario aterriza
        // de vuelta en su app SIN tener que clickar nada más.
        $ret = (string)($pending['return_to'] ?? '');
        if ($ret !== '' && str_starts_with($ret, '/') && !str_starts_with($ret, '//')) {
            DeferredTasks::redirect($ret);
        }
        // Sin return válido (registro fuera de OAuth flow): página estática.
        self::renderVerifyResult(true, 'Cuenta activada y sesión iniciada. Cierra esta pestaña y vuelve a tu aplicación.');
    }

    private static function renderRegisterCheckInbox(string $email, string $devUrl): void
    {
        $emailEsc = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
        $linkBlock = '';
        if ($devUrl !== '') {
            $urlEsc = htmlspecialchars($devUrl, ENT_QUOTES, 'UTF-8');
            $linkBlock = <<<HTML
<p class="hint" style="margin-top:.5rem;">Modo Pruebas (sin SMTP o envío fallido): usa este enlace directamente:</p>
<a href="{$urlEsc}" class="reset-link">{$urlEsc}</a>
HTML;
        }
        self::idpHtmlHeaders();
        $styles = self::idpStyles();
        $brand = self::idpBrandHeader();
        echo <<<HTML
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Confirma tu email · Librería Gabi</title>
<link rel="icon" type="image/png" href="/assets/favicon.png">
{$styles}
<style>
  .ok { margin: 0.5rem 0 0; padding: 0.75rem 0.85rem; background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; border-radius: 8px; font-size: 0.85rem; }
  .reset-link { display: inline-block; margin-top: .6rem; word-break: break-all; font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-size: .76rem; color: #1a2d4e; text-decoration: underline; }
</style>
</head>
<body>
<div class="card">
  {$brand}
  <h1>Confirma tu email</h1>
  <p class="lead">Hemos enviado un enlace de confirmación a <strong>{$emailEsc}</strong>.</p>
  <div class="ok">
    <strong>Revisa tu bandeja de entrada</strong>
    <p class="hint" style="margin-top:.5rem;">Si no lo encuentras, mira en la carpeta de spam. El enlace caduca en 24 horas.</p>
    {$linkBlock}
  </div>
  <footer>
    Una sola cuenta para <span class="gold">Librería Gabi</span>
  </footer>
</div>
</body>
</html>
HTML;
        exit;
    }

    private static function renderVerifyResult(bool $ok, string $message): void
    {
        $messageEsc = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
        $title = $ok ? 'Cuenta confirmada · Librería Gabi' : 'Confirmación fallida · Librería Gabi';
        $h1 = $ok ? '¡Bienvenido!' : 'No se pudo confirmar';
        $blockClass = $ok ? 'ok' : 'err';
        self::idpHtmlHeaders();
        $styles = self::idpStyles();
        $brand = self::idpBrandHeader();
        echo <<<HTML
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$title}</title>
<link rel="icon" type="image/png" href="/assets/favicon.png">
{$styles}
<style>
  .ok { margin: 0.5rem 0 0; padding: 0.75rem 0.85rem; background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; border-radius: 8px; font-size: 0.9rem; }
</style>
</head>
<body>
<div class="card">
  {$brand}
  <h1>{$h1}</h1>
  <p class="{$blockClass}">{$messageEsc}</p>
  <footer>
    Una sola cuenta para <span class="gold">todo el ecosistema</span>
  </footer>
</div>
</body>
</html>
HTML;
        exit;
    }

    private static function renderRegisterForm(string $returnTo, string $error, array $values): void
    {
        $returnEsc = htmlspecialchars($returnTo, ENT_QUOTES, 'UTF-8');
        $nameEsc = htmlspecialchars($values['name'] ?? '', ENT_QUOTES, 'UTF-8');
        $emailEsc = htmlspecialchars($values['email'] ?? '', ENT_QUOTES, 'UTF-8');
        $usernameEsc = htmlspecialchars($values['username'] ?? '', ENT_QUOTES, 'UTF-8');
        $phoneEsc = htmlspecialchars($values['phone'] ?? '', ENT_QUOTES, 'UTF-8');
        $errorBlock = $error !== '' ? '<p class="err">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>' : '';
        $loginHref = '/idp/login?return=' . urlencode($returnTo);
        self::idpHtmlHeaders();
        $styles = self::idpStyles();
        $brand = self::idpBrandHeader();
        $nonce = self::$cspNonce;
        $eyeStyles = self::passwordEyeStyles();
        $eyeScript = self::passwordEyeScript();
        echo <<<HTML
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Crear cuenta · Librería Gabi</title>
<link rel="icon" type="image/png" href="/assets/favicon.png">
{$styles}
{$eyeStyles}
</head>
<body>
<div class="video-container">
  <video autoplay loop muted playsinline class="bg-video">
    <source src="/assets/video-fondo-registrar.mp4" type="video/mp4">
  </video>
</div>
<div class="card">
  {$brand}
  <h1>Crear cuenta</h1>
  <p class="lead">Una cuenta para acceder a Librería Gabi.</p>
  <form method="post" action="/idp/register">
    <input type="hidden" name="return" value="{$returnEsc}">
    <label for="name">Nombre completo
      <input id="name" name="name" type="text" required autofocus autocomplete="name" value="{$nameEsc}" placeholder="Nombre y apellidos">
    </label>
    <label for="username">Nombre de usuario
      <input id="username" name="username" type="text" required pattern="[a-zA-Z0-9_.\\-]{3,32}" autocomplete="username" value="{$usernameEsc}" placeholder="3-32 caracteres · letras, números, . _ -">
    </label>
    <label for="email">Email
      <input id="email" name="email" type="email" required autocomplete="email" value="{$emailEsc}" placeholder="tu@email.com">
    </label>
    <label for="phone"><span>Teléfono</span>
      <input id="phone" name="phone" type="tel" required autocomplete="tel" pattern="[\d\s+\-()]{5,30}" value="{$phoneEsc}" placeholder="+34 600 000 000">
    </label>
    <label for="password">Contraseña
      <div class="pw-wrap" data-reveal="false">
        <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" placeholder="Mínimo 8 caracteres" class="pw-input">
        <button type="button" class="pw-eye" tabindex="-1" aria-label="Mostrar u ocultar la contraseña" aria-pressed="false">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/>
            <circle cx="12" cy="12" r="3"/>
            <line class="pw-eye-strike" x1="4" y1="4" x2="20" y2="20"/>
          </svg>
        </button>
      </div>
    </label>
    <label for="password_confirm">Repite la contraseña
      <div class="pw-wrap" data-reveal="false">
        <input id="password_confirm" name="password_confirm" type="password" required minlength="8" autocomplete="new-password" placeholder="••••••••" class="pw-input">
        <button type="button" class="pw-eye" tabindex="-1" aria-label="Mostrar u ocultar la contraseña" aria-pressed="false">
          <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7Z"/>
            <circle cx="12" cy="12" r="3"/>
            <line class="pw-eye-strike" x1="4" y1="4" x2="20" y2="20"/>
          </svg>
        </button>
      </div>
    </label>
    <button type="submit">Crear cuenta</button>
    {$errorBlock}
  </form>
  <p class="alt-action">¿Ya tienes cuenta? <a href="{$loginHref}">Iniciar sesión</a></p>
  <footer>
    Una sola cuenta para <span class="gold">todo el ecosistema</span>
  </footer>
</div>
<script nonce="{$nonce}">{$eyeScript}</script>
</body>
</html>
HTML;
        exit;
    }

    public static function idpLogout(): void
    {
        self::startSession();

        // Marcamos TODAS las sesiones de este navegador como revocadas en
        // user_sessions, para que dejen de aparecer en "Dispositivos activos".
        if (!empty($_SESSION['session_db_ids']) && is_array($_SESSION['session_db_ids'])) {
            $ids = array_values(array_filter($_SESSION['session_db_ids']));
            if (!empty($ids)) {
                $placeholders = implode(',', array_fill(0, count($ids), '?'));
                $stmt = Database::connect()->prepare(
                    "UPDATE user_sessions SET revoked_at = NOW()
                     WHERE id IN ({$placeholders}) AND revoked_at IS NULL"
                );
                $stmt->execute($ids);
            }
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'], $params['secure'], $params['httponly']);
        }
        session_destroy();

        // Validamos el `return` contra REDIRECT_ALLOWED_ORIGINS para no servir
        // como gadget de open-redirect (atacante podría enviar al usuario a
        // https://evil.com tras logout). Si no es válido, caemos al issuer.
        $candidate = $_GET['return'] ?? '';
        $returnTo = is_string($candidate) && $candidate !== ''
            && Security::isAllowedAbsoluteUrl($candidate, 'REDIRECT_ALLOWED_ORIGINS')
                ? $candidate
                : ServerFactory::issuer();
        header('Location: ' . $returnTo);
        exit;
    }

    /**
     * Cierra UNA cuenta concreta del array idp_users (no toda la sesión IdP).
     * Es el equivalente a Google: "cerrar sesión de esta cuenta" deja las otras
     * activas en el navegador. Si era la activa, se promociona la primera
     * restante; si no queda ninguna, equivale a /idp/logout.
     *
     * Si no hacemos esto y el BFF solo borra su propia entrada (logout-one
     * del BFF), el IdP seguiría reconociendo al usuario en idp_users y el
     * siguiente login auto-SSO lo volvería a meter sin pedir credenciales.
     */
    public static function idpLogoutOne(): void
    {
        self::startSession();
        $userId = (int)($_GET['user'] ?? 0);

        if ($userId > 0) {
            // Marcamos la user_session de este usuario como revocada para que
            // no aparezca en "Dispositivos activos" y para invalidar tokens.
            $sessionDbId = (string)($_SESSION['session_db_ids'][$userId] ?? '');
            if ($sessionDbId !== '') {
                $upd = Database::connect()->prepare(
                    'UPDATE user_sessions SET revoked_at = NOW()
                     WHERE id = :id AND revoked_at IS NULL'
                );
                $upd->execute([':id' => $sessionDbId]);
                unset($_SESSION['session_db_ids'][$userId]);
            }

            if (isset($_SESSION['idp_users']) && is_array($_SESSION['idp_users'])) {
                $_SESSION['idp_users'] = array_values(array_filter(
                    $_SESSION['idp_users'],
                    static fn($id): bool => (int)$id !== $userId
                ));
                if ((int)($_SESSION['idp_active_user'] ?? 0) === $userId) {
                    $_SESSION['idp_active_user'] = $_SESSION['idp_users'][0] ?? null;
                }
            }
        }

        // Si no queda ninguna cuenta, destruimos la sesión IdP entera.
        if (empty($_SESSION['idp_users'])) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000,
                    $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
        }

        $candidate = $_GET['return'] ?? '';
        $returnTo = is_string($candidate) && $candidate !== ''
            && Security::isAllowedAbsoluteUrl($candidate, 'REDIRECT_ALLOWED_ORIGINS')
                ? $candidate
                : ServerFactory::issuer();
        header('Location: ' . $returnTo);
        exit;
    }

    /**
     * Configuración de cuenta. Requiere sesión IdP activa: usa idp_active_user.
     * Permite cambiar nombre, email y contraseña en un solo formulario. Para
     * cambiar la contraseña hay que introducir la actual.
     *
     * Email change: en Pruebas se aplica directo. En prod habría que mandar
     * un confirm-link al nuevo email (TODO MailService).
     */
    /**
     * Configuración de cuenta con layout sidebar + main content (estilo
     * Google/GitHub). Tres secciones independientes — cada una su propio form:
     *   - cuenta:    nombre
     *   - email:     cambio de email (con confirmación por email)
     *   - seguridad: cambio de contraseña (con alerta por email)
     */
    public static function idpSettings(): void
    {
        self::startSession();

        // Sync idp_active_user con la cuenta que la app que llama considera
        // activa. Sin esto, el IdP mostraría la última cuenta logueada por
        // formulario, no la que el usuario tiene seleccionada en el BFF.
        // Validamos que el user esté autorizado (en el array idp_users).
        $requestedUser = (int)($_GET['user'] ?? $_POST['user'] ?? 0);
        if (
            $requestedUser > 0
            && isset($_SESSION['idp_users'])
            && is_array($_SESSION['idp_users'])
            && in_array($requestedUser, array_map('intval', $_SESSION['idp_users']), true)
        ) {
            $_SESSION['idp_active_user'] = $requestedUser;
        }

        // Enforce revocation: si la sesión multi-device del usuario activo
        // está revocada, lo expulsamos antes de mostrar nada.
        self::enforceSessionGuard();

        $activeId = (int)($_SESSION['idp_active_user'] ?? 0);
        if ($activeId <= 0) {
            $here = '/idp/settings';
            if (!empty($_GET['return'])) {
                $here .= '?return=' . urlencode((string)$_GET['return']);
            }
            header('Location: /idp/login?return=' . urlencode($here));
            exit;
        }

        $section = (string)($_GET['section'] ?? $_POST['section'] ?? 'cuenta');
        if (!in_array($section, ['cuenta', 'email', 'seguridad', 'sesiones'], true)) $section = 'cuenta';

        $candidate = (string)($_GET['return'] ?? $_POST['return'] ?? '');
        $returnTo = ($candidate !== '' && Security::isAllowedAbsoluteUrl($candidate, 'REDIRECT_ALLOWED_ORIGINS'))
            ? $candidate
            : '';

        $db = Database::connect();
        $stmt = $db->prepare('SELECT id, email, name, first_name, last_name, phone, username FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $activeId]);
        $user = $stmt->fetch();
        if (!$user) {
            header('Location: /idp/logout');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            self::renderSettingsPage($section, $returnTo, '', '', $user);
            return;
        }

        // POST handling por sección
        if ($section === 'cuenta') {
            // Sub-card identificado por el hidden `field`. Cada form actualiza
            // solo su campo, con validación y mensaje propios.
            $field = (string)($_POST['field'] ?? 'name');

            if ($field === 'name') {
                $name = trim((string)($_POST['name'] ?? ''));
                if ($name === '') {
                    self::renderSettingsPage($section, $returnTo, '', 'El nombre es obligatorio.', array_merge($user, ['name' => $name]));
                    return;
                }
                $parts = explode(' ', $name, 2);
                $upd = $db->prepare(
                    'UPDATE users SET name = :name, first_name = :first, last_name = :last WHERE id = :id'
                );
                $upd->execute([
                    ':name' => $name, ':first' => $parts[0], ':last' => $parts[1] ?? '', ':id' => $activeId,
                ]);
                $fresh = $db->prepare('SELECT id, email, name, first_name, last_name, phone, username FROM users WHERE id = :id LIMIT 1');
                $fresh->execute([':id' => $activeId]);
                self::renderSettingsPage($section, $returnTo, 'Nombre actualizado.', '', $fresh->fetch() ?: $user);
                return;
            }

            if ($field === 'username') {
                $username = trim((string)($_POST['username'] ?? ''));
                if ($username === '' || !preg_match('/^[a-zA-Z0-9_.-]{3,32}$/', $username)) {
                    self::renderSettingsPage($section, $returnTo, '', 'Usuario: 3-32 caracteres, letras/números/punto/guion.', array_merge($user, ['username' => $username]));
                    return;
                }
                if ($username !== (string)($user['username'] ?? '')) {
                    $check = $db->prepare('SELECT id FROM users WHERE username = :u AND id <> :id LIMIT 1');
                    $check->execute([':u' => $username, ':id' => $activeId]);
                    if ($check->fetch()) {
                        self::renderSettingsPage($section, $returnTo, '', 'Ese nombre de usuario ya está en uso.', array_merge($user, ['username' => $username]));
                        return;
                    }
                }
                $upd = $db->prepare('UPDATE users SET username = :u WHERE id = :id');
                $upd->execute([':u' => $username, ':id' => $activeId]);
                $fresh = $db->prepare('SELECT id, email, name, first_name, last_name, phone, username FROM users WHERE id = :id LIMIT 1');
                $fresh->execute([':id' => $activeId]);
                self::renderSettingsPage($section, $returnTo, 'Nombre de usuario actualizado.', '', $fresh->fetch() ?: $user);
                return;
            }

            if ($field === 'phone') {
                $phone = trim((string)($_POST['phone'] ?? ''));
                // Phone es opcional. Si se manda vacío, lo borramos (NULL).
                // Si viene con contenido, validamos formato laxo.
                if ($phone !== '' && !preg_match('/^[\d\s+\-()]{5,30}$/', $phone)) {
                    self::renderSettingsPage($section, $returnTo, '', 'Formato de teléfono no válido (5-30 caracteres, solo dígitos y + - ( )).', array_merge($user, ['phone' => $phone]));
                    return;
                }
                $upd = $db->prepare('UPDATE users SET phone = :phone WHERE id = :id');
                $upd->execute([':phone' => $phone !== '' ? $phone : null, ':id' => $activeId]);
                $fresh = $db->prepare('SELECT id, email, name, first_name, last_name, phone, username FROM users WHERE id = :id LIMIT 1');
                $fresh->execute([':id' => $activeId]);
                $msg = $phone === '' ? 'Teléfono eliminado.' : 'Teléfono actualizado.';
                self::renderSettingsPage($section, $returnTo, $msg, '', $fresh->fetch() ?: $user);
                return;
            }
        }

        if ($section === 'email') {
            $newEmail = trim(strtolower((string)($_POST['new_email'] ?? '')));
            if ($newEmail === '' || !filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
                self::renderSettingsPage($section, $returnTo, '', 'Email no válido.', $user);
                return;
            }
            if ($newEmail === strtolower($user['email'])) {
                self::renderSettingsPage($section, $returnTo, '', 'El nuevo email es igual al actual.', $user);
                return;
            }
            $check = $db->prepare('SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1');
            $check->execute([':email' => $newEmail, ':id' => $activeId]);
            if ($check->fetch()) {
                self::renderSettingsPage($section, $returnTo, '', 'Ese email ya está en uso por otra cuenta.', $user);
                return;
            }

            // Pareja de tokens: confirm (al NUEVO email) + deny (al ACTUAL).
            // Si el usuario que recibe el notice en su email actual NO solicitó
            // el cambio (cuenta comprometida), el deny token revoca todo y
            // dispara reset de contraseña.
            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);
            $rawDenyToken = bin2hex(random_bytes(32));
            $denyTokenHash = hash('sha256', $rawDenyToken);
            $expiresAt = date('Y-m-d H:i:s', time() + 3600);

            $clean = $db->prepare('DELETE FROM email_change_tokens WHERE user_id = :uid');
            $clean->execute([':uid' => $activeId]);
            $ins = $db->prepare(
                'INSERT INTO email_change_tokens (user_id, new_email, token_hash, deny_token_hash, expires_at)
                 VALUES (:uid, :new, :hash, :deny, :exp)'
            );
            $ins->execute([
                ':uid' => $activeId, ':new' => $newEmail,
                ':hash' => $tokenHash, ':deny' => $denyTokenHash, ':exp' => $expiresAt,
            ]);

            $confirmUrl = ServerFactory::issuer() . '/idp/confirm-email-change?token=' . $rawToken;
            $denyUrl = ServerFactory::issuer() . '/idp/email-change-deny?token=' . $rawDenyToken;

            $msg = "Modo Pruebas (sin SMTP). Enlace de confirmación: {$confirmUrl}";
            if (MailService::isConfigured()) {
                $sentConfirm = MailService::sendEmailChangeConfirm($newEmail, $confirmUrl, $user['name']);
                // Notificación al email actual — patrón Google: avisar SIEMPRE,
                // independientemente del éxito del confirm, para que el dueño
                // legítimo pueda abortar si la cuenta está comprometida.
                MailService::sendEmailChangeNotice($user['email'], $user['name'], $newEmail, $denyUrl);
                $msg = $sentConfirm
                    ? "Hemos enviado un correo de confirmación a {$newEmail} y una notificación a {$user['email']}. Tu email actual no cambiará hasta que confirmes."
                    : "No se pudo enviar el correo de confirmación. Usa este enlace para confirmar: {$confirmUrl}";
            }
            self::renderSettingsPage($section, $returnTo, $msg, '', $user);
            return;
        }

        if ($section === 'seguridad') {
            $currentPassword = (string)($_POST['current_password'] ?? '');
            $newPassword = (string)($_POST['new_password'] ?? '');
            $newPasswordConfirm = (string)($_POST['new_password_confirm'] ?? '');

            $hashStmt = $db->prepare('SELECT password FROM users WHERE id = :id LIMIT 1');
            $hashStmt->execute([':id' => $activeId]);
            $hashRow = $hashStmt->fetch();
            if (!$hashRow || !password_verify($currentPassword, $hashRow['password'])) {
                self::renderSettingsPage($section, $returnTo, '', 'La contraseña actual es incorrecta.', $user);
                return;
            }
            if (strlen($newPassword) < 8) {
                self::renderSettingsPage($section, $returnTo, '', 'La nueva contraseña debe tener al menos 8 caracteres.', $user);
                return;
            }
            if ($newPassword !== $newPasswordConfirm) {
                self::renderSettingsPage($section, $returnTo, '', 'Las contraseñas nuevas no coinciden.', $user);
                return;
            }

            $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $up = $db->prepare('UPDATE users SET password = :pass WHERE id = :id');
            $up->execute([':pass' => $newHash, ':id' => $activeId]);

            if (MailService::isConfigured()) {
                MailService::sendPasswordChangedAlert($user['email'], $user['name']);
            }
            self::renderSettingsPage($section, $returnTo, 'Contraseña actualizada. Te hemos enviado una alerta de seguridad por email.', '', $user);
            return;
        }
    }

    /**
     * Confirmación del cambio de email. El usuario llega aquí desde el clic
     * en el botón del email enviado al nuevo email. Si el token es válido,
     * aplica el cambio sobre users.email.
     */
    public static function idpConfirmEmailChange(): void
    {
        $token = (string)($_GET['token'] ?? '');
        if ($token === '' || !ctype_xdigit($token) || strlen($token) !== 64) {
            self::renderVerifyResult(false, 'Enlace inválido o caducado.');
            return;
        }

        $tokenHash = hash('sha256', $token);
        $db = Database::connect();
        $stmt = $db->prepare(
            'SELECT id, user_id, new_email FROM email_change_tokens
             WHERE token_hash = :hash AND used_at IS NULL AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([':hash' => $tokenHash]);
        $row = $stmt->fetch();
        if (!$row) {
            self::renderVerifyResult(false, 'Enlace inválido o caducado.');
            return;
        }

        // Verificar que el email destino no fue tomado por otro usuario.
        $check = $db->prepare('SELECT id FROM users WHERE email = :email AND id <> :uid LIMIT 1');
        $check->execute([':email' => $row['new_email'], ':uid' => $row['user_id']]);
        if ($check->fetch()) {
            $del = $db->prepare('DELETE FROM email_change_tokens WHERE id = :id');
            $del->execute([':id' => $row['id']]);
            self::renderVerifyResult(false, 'El nuevo email ya está siendo usado por otra cuenta.');
            return;
        }

        $db->beginTransaction();
        try {
            $upd = $db->prepare('UPDATE users SET email = :email WHERE id = :id');
            $upd->execute([':email' => $row['new_email'], ':id' => $row['user_id']]);
            $mark = $db->prepare('UPDATE email_change_tokens SET used_at = NOW() WHERE id = :id');
            $mark->execute([':id' => $row['id']]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            self::renderVerifyResult(false, 'No se pudo aplicar el cambio. Inténtalo de nuevo.');
            return;
        }

        self::renderVerifyResult(true, 'Email actualizado. Cierra esta pestaña y vuelve a tu aplicación.');
    }

    /**
     * Endpoint del botón "No fui yo, cancelar y proteger mi cuenta" del email
     * de notificación enviado al email ACTUAL. Cancela la solicitud de cambio,
     * revoca todas las sesiones del usuario y le manda a resetear contraseña.
     */
    public static function idpEmailChangeDeny(): void
    {
        $token = (string)($_GET['token'] ?? '');
        if ($token === '' || !ctype_xdigit($token) || strlen($token) !== 64) {
            self::renderVerifyResult(false, 'Enlace inválido o caducado.');
            return;
        }

        $tokenHash = hash('sha256', $token);
        $db = Database::connect();
        $stmt = $db->prepare(
            'SELECT id, user_id FROM email_change_tokens
             WHERE deny_token_hash = :hash AND used_at IS NULL AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([':hash' => $tokenHash]);
        $row = $stmt->fetch();
        if (!$row) {
            self::renderVerifyResult(false, 'Enlace inválido o caducado.');
            return;
        }

        $userId = (int)$row['user_id'];
        $db->beginTransaction();
        try {
            // 1) Marca el token de cambio como usado (action=denied) → el
            //    confirm también deja de ser válido.
            $mark = $db->prepare('UPDATE email_change_tokens SET used_at = NOW() WHERE id = :id');
            $mark->execute([':id' => $row['id']]);

            // 2) Revoca TODAS las sesiones del usuario en todos sus
            //    dispositivos (asumimos compromiso).
            $rev = $db->prepare(
                'UPDATE user_sessions SET revoked_at = NOW()
                 WHERE user_id = :uid AND revoked_at IS NULL'
            );
            $rev->execute([':uid' => $userId]);

            // 3) Genera un password reset token y prepara la redirección.
            $rawReset = bin2hex(random_bytes(32));
            $resetHash = hash('sha256', $rawReset);
            $resetExp = date('Y-m-d H:i:s', time() + 3600);
            $cleanReset = $db->prepare('DELETE FROM password_reset_tokens WHERE user_id = :uid');
            $cleanReset->execute([':uid' => $userId]);
            $insReset = $db->prepare(
                'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
                 VALUES (:uid, :hash, :exp)'
            );
            $insReset->execute([':uid' => $userId, ':hash' => $resetHash, ':exp' => $resetExp]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            self::renderVerifyResult(false, 'No se pudo procesar la cancelación. Inténtalo de nuevo.');
            return;
        }

        header('Location: /idp/reset-password?token=' . $rawReset);
        exit;
    }

    /**
     * Renderiza la página de settings con sidebar (estilo Google account):
     *   - Sidebar izquierda con secciones (Cuenta, Email, Seguridad)
     *   - Main content con el form de la sección activa
     *   - Header superior con marca + nombre del usuario + volver a la app
     */
    private static function renderSettingsPage(string $section, string $returnTo, string $success, string $error, array $user): void
    {
        $returnEsc = htmlspecialchars($returnTo, ENT_QUOTES, 'UTF-8');
        $nameEsc = htmlspecialchars((string)($user['name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $emailEsc = htmlspecialchars((string)($user['email'] ?? ''), ENT_QUOTES, 'UTF-8');
        $usernameEsc = htmlspecialchars((string)($user['username'] ?? ''), ENT_QUOTES, 'UTF-8');
        $phoneEsc = htmlspecialchars((string)($user['phone'] ?? ''), ENT_QUOTES, 'UTF-8');
        $userInitial = strtoupper(mb_substr(trim((string)($user['name'] ?? 'U')), 0, 1));

        // Role badge — el usuario no está en $user, hay que cargarlo aparte.
        // Roles: 'admin' (administrador global), 'real' (Premium), 'user' (estándar).
        $roleStmt = Database::connect()->prepare('SELECT role FROM users WHERE id = :id LIMIT 1');
        $roleStmt->execute([':id' => (int)$user['id']]);
        $roleRow = $roleStmt->fetch();
        $role = (string)($roleRow['role'] ?? 'user');
        $roleLabel = match ($role) {
            'admin' => '★ Administrador',
            'empleado' => 'Empleado',
            'real' => 'Premium',
            default => 'Usuario',
        };
        $roleClass = match ($role) {
            'admin' => 'settings__role--admin',
            'empleado' => 'settings__role--empleado',
            'real' => 'settings__role--real',
            default => '',
        };
        $roleBadge = '<span class="settings__role ' . $roleClass . '">' . $roleLabel . '</span>';
        $errorBlock = $error !== '' ? '<p class="err">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>' : '';
        $okBlock = $success !== '' ? '<p class="ok">' . htmlspecialchars($success, ENT_QUOTES, 'UTF-8') . '</p>' : '';

        // Sidebar nav: cada item conserva el return en el href.
        $navItems = [
            'cuenta' => ['icon' => '👤', 'label' => 'Datos personales', 'desc' => 'Tu nombre'],
            'email' => ['icon' => '✉', 'label' => 'Email', 'desc' => 'Tu email de acceso'],
            'seguridad' => ['icon' => '🔒', 'label' => 'Seguridad', 'desc' => 'Cambiar contraseña'],
            'sesiones' => ['icon' => '🖥', 'label' => 'Sesiones', 'desc' => 'Dispositivos activos'],
        ];
        $returnQs = $returnTo !== '' ? '&return=' . urlencode($returnTo) : '';
        $navHtml = '';
        foreach ($navItems as $key => $meta) {
            $active = $section === $key ? ' is-active' : '';
            $navHtml .= '<a class="settings__nav-item' . $active . '" href="/idp/settings?section=' . $key . $returnQs . '">'
                . '<span class="settings__nav-icon">' . $meta['icon'] . '</span>'
                . '<span class="settings__nav-text">'
                . '<span class="settings__nav-label">' . $meta['label'] . '</span>'
                . '<span class="settings__nav-desc">' . $meta['desc'] . '</span>'
                . '</span></a>';
        }

        $backLink = $returnTo !== ''
            ? '<a class="settings__back" href="' . $returnEsc . '">← Volver a la app</a>'
            : '';

        // Render del contenido según sección.
        $sectionHtml = match ($section) {
            'cuenta' => self::renderSectionCuenta($returnEsc, $nameEsc, $usernameEsc, $phoneEsc, $errorBlock, $okBlock),
            'email' => self::renderSectionEmail($returnEsc, $emailEsc, $errorBlock, $okBlock),
            'seguridad' => self::renderSectionSeguridad($returnEsc, $errorBlock, $okBlock),
            'sesiones' => self::renderSectionSesiones((int)$user['id'], $returnEsc, $errorBlock, $okBlock),
            default => '',
        };

        self::idpHtmlHeaders();
        $styles = self::idpStyles();
        echo <<<HTML
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Configurar cuenta · Librería Gabi</title>
<link rel="icon" type="image/png" href="/assets/favicon.png">
{$styles}
<style>
  body { padding: 0; display: block; }
  .settings {
    display: grid;
    grid-template-columns: 280px 1fr;
    min-height: 100vh;
  }
  .settings__sidebar {
    background: #faf8f3;
    border-right: 1px solid var(--border);
    padding: 1.5rem 1rem;
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
  }
  .settings__brand {
    display: flex; align-items: center; gap: 0.65rem;
    padding: 0.5rem 0.75rem 1.25rem;
    border-bottom: 1px solid var(--border);
    margin-bottom: 0.75rem;
  }
  .settings__brand img { width: 30px; height: 30px; object-fit: contain; }
  .settings__brand-text { display: flex; flex-direction: column; line-height: 1.15; }
  .settings__brand-name { font-weight: 700; font-size: 0.95rem; color: var(--navy); }
  .settings__brand-tag {
    font-size: 0.58rem; text-transform: uppercase; letter-spacing: 0.14em; color: var(--muted);
  }
  .settings__nav-item {
    display: flex; align-items: center; gap: 0.7rem;
    padding: 0.6rem 0.75rem;
    border-radius: 10px;
    color: var(--navy);
    text-decoration: none;
    transition: background 0.15s, color 0.15s;
  }
  .settings__nav-item:hover { background: rgba(26, 45, 78, 0.05); }
  .settings__nav-item.is-active {
    background: var(--navy); color: #fff;
  }
  .settings__nav-item.is-active .settings__nav-desc { color: rgba(255,255,255,0.7); }
  .settings__nav-icon {
    width: 28px; height: 28px;
    display: grid; place-items: center;
    background: rgba(201, 168, 76, 0.18);
    border-radius: 8px;
    font-size: 0.95rem;
  }
  .settings__nav-item.is-active .settings__nav-icon { background: rgba(255,255,255,0.16); }
  .settings__nav-text { display: flex; flex-direction: column; line-height: 1.2; }
  .settings__nav-label { font-size: 0.88rem; font-weight: 600; }
  .settings__nav-desc { font-size: 0.72rem; color: var(--muted); }

  .settings__main {
    padding: 2.5rem 3rem;
    max-width: 720px;
    width: 100%;
  }
  .settings__header {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 2rem;
  }
  .settings__user-pill {
    display: flex; align-items: center; gap: 0.6rem;
    padding: 0.4rem 0.8rem 0.4rem 0.4rem;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 999px;
  }
  .settings__user-initial {
    width: 32px; height: 32px;
    display: grid; place-items: center;
    background: var(--navy); color: #fff;
    border-radius: 50%;
    font-weight: 700; font-size: 0.85rem;
  }
  .settings__user-info { display: flex; flex-direction: column; line-height: 1.2; }
  .settings__user-name { font-size: 0.85rem; font-weight: 600; color: var(--navy); display: inline-flex; align-items: center; gap: 0.4rem; }
  .settings__user-email { font-size: 0.72rem; color: var(--muted); }
  .settings__role {
    display: inline-flex;
    padding: 0.12rem 0.5rem;
    background: rgba(90, 100, 120, 0.12);
    color: var(--muted);
    border-radius: 999px;
    font-size: 0.65rem;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
  }
  .settings__role--admin {
    background: linear-gradient(135deg, #c9a84c 0%, #b8943a 100%);
    color: #1a2d4e;
    box-shadow: 0 2px 6px rgba(201, 168, 76, 0.35);
  }
  .settings__role--real {
    background: linear-gradient(135deg, #1a2d4e 0%, #243a63 100%);
    color: #fff;
    box-shadow: 0 2px 6px rgba(26, 45, 78, 0.3);
  }
  .settings__role--empleado {
    background: linear-gradient(135deg, #4a90c9 0%, #2e6fa0 100%);
    color: #fff;
    box-shadow: 0 2px 6px rgba(74, 144, 201, 0.3);
  }

  .settings__section {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 14px;
    padding: 2rem;
    box-shadow: 0 18px 40px rgba(17, 30, 51, 0.06);
  }
  .settings__section h2 {
    margin: 0 0 0.4rem;
    font-size: 1.3rem;
    color: var(--navy);
  }
  .settings__section p.lead { margin: 0 0 1.5rem; font-size: 0.9rem; }

  /* Sub-cards: bloques internos para acciones atómicas dentro de una sección */
  .subcard {
    background: var(--cream);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 1.15rem 1.25rem;
    margin-bottom: 1rem;
  }
  .subcard:last-of-type { margin-bottom: 0; }
  .subcard__title {
    margin: 0 0 0.25rem;
    font-size: 0.95rem;
    font-weight: 700;
    color: var(--navy);
  }
  .subcard__hint {
    margin: 0 0 0.85rem;
    font-size: 0.78rem;
    color: var(--muted);
  }
  .subcard__form {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 0.6rem;
    align-items: stretch;
  }
  .subcard__form input[type="text"],
  .subcard__form input[type="email"],
  .subcard__form input[type="tel"] {
    margin: 0;
    padding: 0.6rem 0.8rem;
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 9px;
    font-size: 0.88rem;
    color: var(--navy);
    font-family: inherit;
    transition: border-color 0.18s, box-shadow 0.18s;
  }
  .subcard__form input:focus {
    outline: none;
    border-color: var(--gold);
    box-shadow: 0 0 0 3px rgba(201, 168, 76, 0.18);
  }
  .subcard__btn {
    padding: 0.6rem 1rem;
    background: var(--navy);
    color: #fff;
    border: 0;
    border-radius: 9px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    font-family: inherit;
    transition: background 0.18s, transform 0.1s;
    white-space: nowrap;
  }
  .subcard__btn:hover { background: var(--navy-soft); }
  .subcard__btn:active { transform: translateY(1px); }
  /* En móvil el botón cae debajo del input para no apretarse */
  @media (max-width: 540px) {
    .subcard__form { grid-template-columns: 1fr; }
  }
  .visually-hidden {
    position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
    overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0;
  }

  .ok {
    margin: 0.5rem 0 0;
    padding: 0.65rem 0.8rem;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    color: #065f46;
    border-radius: 8px;
    font-size: 0.85rem;
  }

  .settings__back {
    display: inline-flex; align-items: center; gap: 0.3rem;
    font-size: 0.85rem; font-weight: 600;
    color: var(--navy); text-decoration: none;
    padding: 0.5rem 0.85rem;
    border: 1px solid var(--border);
    border-radius: 8px;
    background: #fff;
    transition: border-color 0.15s, background 0.15s;
  }
  .settings__back:hover { border-color: var(--gold); }

  .settings__readonly {
    padding: 0.7rem 0.85rem;
    background: #f3f4f6;
    border: 1px dashed var(--border);
    border-radius: 10px;
    font-size: 0.9rem;
    color: var(--muted);
    font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
  }

  @media (max-width: 760px) {
    .settings { grid-template-columns: 1fr; }
    .settings__sidebar {
      border-right: 0;
      border-bottom: 1px solid var(--border);
      flex-direction: row;
      flex-wrap: wrap;
      overflow-x: auto;
    }
    .settings__brand { width: 100%; border-bottom: 0; padding-bottom: 0.5rem; margin-bottom: 0.5rem; }
    .settings__nav-item { flex: 1 1 auto; }
    .settings__nav-text { display: none; }
    .settings__nav-item .settings__nav-icon { margin: 0 auto; }
    .settings__main { padding: 1.5rem 1.25rem; }
    .settings__header { flex-direction: column; gap: 1rem; align-items: flex-start; }
  }
</style>
</head>
<body>
<div class="settings">
  <aside class="settings__sidebar">
    <div class="settings__brand">
      <img src="/assets/logo.png" alt="Librería Gabi" width="30" height="30">
      <div class="settings__brand-text">
        <span class="settings__brand-name">Mi cuenta</span>
        <span class="settings__brand-tag">Librería Gabi IdP</span>
      </div>
    </div>
    {$navHtml}
  </aside>
  <main class="settings__main">
    <header class="settings__header">
      <div class="settings__user-pill">
        <span class="settings__user-initial">{$userInitial}</span>
        <div class="settings__user-info">
          <span class="settings__user-name">{$nameEsc} {$roleBadge}</span>
          <span class="settings__user-email">{$emailEsc}</span>
        </div>
      </div>
      {$backLink}
    </header>
    <div class="settings__section">
      {$sectionHtml}
    </div>
  </main>
</div>
</body>
</html>
HTML;
        exit;
    }

    private static function renderSectionCuenta(string $returnEsc, string $nameEsc, string $usernameEsc, string $phoneEsc, string $errorBlock, string $okBlock): string
    {
        return <<<HTML
<h2>Datos personales</h2>
<p class="lead">Tu identidad en Librería Gabi. Edita cada bloque por separado.</p>

<div class="subcard">
  <h3 class="subcard__title">Nombre completo</h3>
  <p class="subcard__hint">Es el nombre que aparece en tu cuenta y notificaciones.</p>
  <form method="post" action="/idp/settings" class="subcard__form">
    <input type="hidden" name="section" value="cuenta">
    <input type="hidden" name="field" value="name">
    <input type="hidden" name="return" value="{$returnEsc}">
    <label for="name" class="visually-hidden">Nombre completo</label>
    <input id="name" name="name" type="text" required value="{$nameEsc}" placeholder="Nombre y apellidos">
    <button type="submit" class="subcard__btn">Guardar nombre</button>
  </form>
</div>

<div class="subcard">
  <h3 class="subcard__title">Nombre de usuario</h3>
  <p class="subcard__hint">Identificador único; sirve para iniciar sesión además del email.</p>
  <form method="post" action="/idp/settings" class="subcard__form">
    <input type="hidden" name="section" value="cuenta">
    <input type="hidden" name="field" value="username">
    <input type="hidden" name="return" value="{$returnEsc}">
    <label for="username" class="visually-hidden">Nombre de usuario</label>
    <input id="username" name="username" type="text" required pattern="[a-zA-Z0-9_.\\-]{3,32}" value="{$usernameEsc}" placeholder="3-32 caracteres · letras, números, . _ -">
    <button type="submit" class="subcard__btn">Guardar usuario</button>
  </form>
</div>

<div class="subcard">
  <h3 class="subcard__title">Teléfono <span class="subcard__optional">(opcional)</span></h3>
  <p class="subcard__hint">Usado por el equipo del despacho para contactarte. Déjalo en blanco para eliminarlo.</p>
  <form method="post" action="/idp/settings" class="subcard__form">
    <input type="hidden" name="section" value="cuenta">
    <input type="hidden" name="field" value="phone">
    <input type="hidden" name="return" value="{$returnEsc}">
    <label for="phone" class="visually-hidden">Teléfono</label>
    <input id="phone" name="phone" type="tel" pattern="[\d\s+\-()]{5,30}" value="{$phoneEsc}" placeholder="+34 600 000 000">
    <button type="submit" class="subcard__btn">Guardar teléfono</button>
  </form>
</div>

{$okBlock}
{$errorBlock}
HTML;
    }

    private static function renderSectionEmail(string $returnEsc, string $emailEsc, string $errorBlock, string $okBlock): string
    {
        return <<<HTML
<h2>Email</h2>
<p class="lead">Tu email de acceso al ecosistema. El cambio requiere confirmación desde el email nuevo.</p>
<label>Email actual
  <div class="settings__readonly">{$emailEsc}</div>
</label>
<form method="post" action="/idp/settings" style="margin-top:1rem;">
  <input type="hidden" name="section" value="email">
  <input type="hidden" name="return" value="{$returnEsc}">
  <label for="new_email">Nuevo email
    <input id="new_email" name="new_email" type="email" required placeholder="nuevo@email.com">
  </label>
  <button type="submit">Solicitar cambio de email</button>
  {$okBlock}
  {$errorBlock}
</form>
HTML;
    }

    private static function renderSectionSeguridad(string $returnEsc, string $errorBlock, string $okBlock): string
    {
        return <<<HTML
<h2>Seguridad</h2>
<p class="lead">Cambia tu contraseña. Te enviaremos una alerta por email tras el cambio.</p>
<form method="post" action="/idp/settings">
  <input type="hidden" name="section" value="seguridad">
  <input type="hidden" name="return" value="{$returnEsc}">
  <label for="current_password">Contraseña actual
    <input id="current_password" name="current_password" type="password" required autocomplete="current-password">
  </label>
  <label for="new_password">Nueva contraseña
    <input id="new_password" name="new_password" type="password" required minlength="8" autocomplete="new-password" placeholder="Mínimo 8 caracteres">
  </label>
  <label for="new_password_confirm">Repite la nueva contraseña
    <input id="new_password_confirm" name="new_password_confirm" type="password" required minlength="8" autocomplete="new-password">
  </label>
  <button type="submit">Cambiar contraseña</button>
  {$okBlock}
  {$errorBlock}
</form>
HTML;
    }

    /**
     * Renderiza la sección "Sesiones": lista de dispositivos activos del usuario
     * con metadatos (UA, país, IP, last_seen) y botón para revocar cada uno.
     * La sesión actual se marca con un badge "Esta sesión" y no se puede revocar.
     */
    private static function renderSectionSesiones(int $userId, string $returnEsc, string $errorBlock, string $okBlock): string
    {
        $db = Database::connect();
        $stmt = $db->prepare(
            'SELECT id, ip, country_code, country_name, device_label, last_seen, created_at
             FROM user_sessions
             WHERE user_id = :uid AND revoked_at IS NULL
             ORDER BY last_seen DESC'
        );
        $stmt->execute([':uid' => $userId]);
        $sessions = $stmt->fetchAll();
        $currentId = (string)($_SESSION['session_db_ids'][$userId] ?? '');

        if (empty($sessions)) {
            $listHtml = '<p class="hint">No hay sesiones activas registradas.</p>';
        } else {
            $rows = '';
            foreach ($sessions as $s) {
                $deviceEsc = htmlspecialchars((string)($s['device_label'] ?? 'Desconocido'), ENT_QUOTES, 'UTF-8');
                $ipEsc = htmlspecialchars((string)($s['ip'] ?? ''), ENT_QUOTES, 'UTF-8');
                $countryEsc = htmlspecialchars((string)($s['country_name'] ?? $s['country_code'] ?? 'Desconocida'), ENT_QUOTES, 'UTF-8');
                $lastSeen = self::relativeTime((string)$s['last_seen']);
                $isCurrent = $s['id'] === $currentId;
                $actionHtml = $isCurrent
                    ? '<span class="session-current">Esta sesión</span>'
                    : '<form method="post" action="/idp/revoke-session" style="margin:0;">'
                        . '<input type="hidden" name="id" value="' . htmlspecialchars($s['id'], ENT_QUOTES, 'UTF-8') . '">'
                        . '<input type="hidden" name="return" value="' . $returnEsc . '">'
                        . '<button type="submit" class="session-revoke">Cerrar</button>'
                        . '</form>';
                $rows .= <<<HTML
<li class="session-item">
  <div class="session-info">
    <span class="session-device">{$deviceEsc}</span>
    <span class="session-meta">{$countryEsc} · IP {$ipEsc} · {$lastSeen}</span>
  </div>
  <div class="session-action">{$actionHtml}</div>
</li>
HTML;
            }
            $listHtml = '<ul class="session-list">' . $rows . '</ul>';
        }

        return <<<HTML
<h2>Sesiones activas</h2>
<p class="lead">Dispositivos en los que tu cuenta tiene sesión abierta. Si ves algo que no reconoces, ciérralo.</p>
{$okBlock}
{$errorBlock}
{$listHtml}
<style>
  .session-list { list-style: none; padding: 0; margin: 1rem 0 0; display: grid; gap: 0.75rem; }
  .session-item {
    display: flex; align-items: center; justify-content: space-between; gap: 1rem;
    padding: 0.85rem 1rem;
    background: #faf8f3;
    border: 1px solid var(--border);
    border-radius: 10px;
  }
  .session-info { display: flex; flex-direction: column; gap: 0.2rem; min-width: 0; }
  .session-device { font-size: 0.92rem; font-weight: 600; color: var(--navy); }
  .session-meta { font-size: 0.78rem; color: var(--muted); }
  .session-current {
    padding: 0.25rem 0.7rem;
    background: rgba(22, 163, 74, 0.12);
    color: #166534;
    border-radius: 999px;
    font-size: 0.72rem;
    font-weight: 600;
  }
  .session-revoke {
    margin: 0;
    padding: 0.4rem 0.85rem;
    background: #fff;
    color: #b91c1c;
    border: 1px solid #fecaca;
    border-radius: 8px;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s;
  }
  .session-revoke:hover { background: #fef2f2; }
</style>
HTML;
    }

    /**
     * Formatea un timestamp DB a string relativo: "hace 5 min", "hace 2 días".
     */
    private static function relativeTime(string $datetime): string
    {
        $t = strtotime($datetime);
        if ($t === false) return $datetime;
        $diff = time() - $t;
        if ($diff < 60) return 'hace un momento';
        if ($diff < 3600) return 'hace ' . (int)floor($diff / 60) . ' min';
        if ($diff < 86400) return 'hace ' . (int)floor($diff / 3600) . ' h';
        if ($diff < 86400 * 30) return 'hace ' . (int)floor($diff / 86400) . ' días';
        return date('d/m/Y', $t);
    }

    /**
     * Revoca una sesión activa del usuario actual. Solo el dueño puede revocar
     * sus propias sesiones. Tras revocar, la próxima vez que esa sesión intente
     * autorizarse en /oauth/authorize fallará (ver enforcement en authorize()).
     */
    public static function idpRevokeSession(): void
    {
        self::startSession();
        $activeId = (int)($_SESSION['idp_active_user'] ?? 0);
        if ($activeId <= 0) {
            header('Location: /idp/login');
            exit;
        }
        $id = (string)($_POST['id'] ?? '');
        if ($id !== '' && ctype_xdigit($id) && strlen($id) === 64) {
            $upd = Database::connect()->prepare(
                'UPDATE user_sessions SET revoked_at = NOW()
                 WHERE id = :id AND user_id = :uid AND revoked_at IS NULL'
            );
            $upd->execute([':id' => $id, ':uid' => $activeId]);
        }
        // Volver a la pantalla de sesiones preservando return.
        $candidate = (string)($_POST['return'] ?? '');
        $returnTo = ($candidate !== '' && Security::isAllowedAbsoluteUrl($candidate, 'REDIRECT_ALLOWED_ORIGINS'))
            ? '?section=sesiones&return=' . urlencode($candidate)
            : '?section=sesiones';
        header('Location: /idp/settings' . $returnTo);
        exit;
    }

    /**
     * Pide email al usuario y, si existe la cuenta, genera un token de reset.
     * Pruebas: el link se imprime en pantalla (no hay MailService).
     * Prod TODO: enviar el link por email y solo decir "si existe te llegará".
     */
    public static function idpForgotPassword(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            self::renderForgotPasswordForm('', '', '');
            return;
        }

        $email = trim(strtolower((string)($_POST['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            self::renderForgotPasswordForm('', 'Email no válido.', '');
            return;
        }

        $db = Database::connect();

        // Rate-limit: máx. 3 requests por email/hora + 10 por IP/hora. Previene
        // a) spam de emails a una víctima concreta b) gasto masivo de cuota SMTP.
        $ip = Security::getClientIp();
        $emailCheck = $db->prepare(
            'SELECT COUNT(*) AS n FROM password_reset_requests
             WHERE email = :email AND created_at > NOW() - INTERVAL 1 HOUR'
        );
        $emailCheck->execute([':email' => $email]);
        if ((int)$emailCheck->fetch()['n'] >= 3) {
            self::renderForgotPasswordForm('', 'Demasiados intentos para este email. Espera una hora y vuelve a probar.', '');
            return;
        }
        $ipCheck = $db->prepare(
            'SELECT COUNT(*) AS n FROM password_reset_requests
             WHERE ip = :ip AND created_at > NOW() - INTERVAL 1 HOUR'
        );
        $ipCheck->execute([':ip' => $ip]);
        if ((int)$ipCheck->fetch()['n'] >= 10) {
            self::renderForgotPasswordForm('', 'Demasiados intentos desde tu IP. Espera una hora.', '');
            return;
        }

        // Registramos el intento ANTES de comprobar si el usuario existe, para
        // que el contador no se pueda burlar haciendo fishing de emails inválidos.
        $logReq = $db->prepare('INSERT INTO password_reset_requests (email, ip) VALUES (:email, :ip)');
        $logReq->execute([':email' => $email, ':ip' => $ip]);

        $stmt = $db->prepare('SELECT id FROM users WHERE email = :email AND banned_at IS NULL LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();

        if (!$user) {
            // En Pruebas mostramos el error real para diagnosticar; en prod
            // siempre devolveríamos OK ambiguo para no leakear emails registrados.
            self::renderForgotPasswordForm('', 'No hay cuenta con ese email.', '');
            return;
        }

        // Token raw → URL; hash SHA-256 → BD. Solo el hash es persistido.
        $rawToken = bin2hex(random_bytes(32)); // 64 chars hex
        $tokenHash = hash('sha256', $rawToken);
        $expiresAt = date('Y-m-d H:i:s', time() + 3600); // 1 hora

        // Limpia tokens previos del usuario (uno activo a la vez).
        $clean = $db->prepare('DELETE FROM password_reset_tokens WHERE user_id = :uid');
        $clean->execute([':uid' => $user['id']]);

        $insert = $db->prepare(
            'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
             VALUES (:uid, :hash, :exp)'
        );
        $insert->execute([':uid' => $user['id'], ':hash' => $tokenHash, ':exp' => $expiresAt]);

        $resetUrl = ServerFactory::issuer() . '/idp/reset-password?token=' . $rawToken;

        // Si SMTP está configurado, enviamos por email y NO mostramos el link.
        // Si no está configurado (Pruebas sin SMTP), fallback a mostrarlo en pantalla.
        if (MailService::isConfigured()) {
            $sent = MailService::sendPasswordReset($email, $resetUrl);
            if ($sent) {
                self::renderForgotPasswordForm('', '', 'Te hemos enviado un enlace por email. Revisa tu bandeja (y la carpeta de spam).');
            } else {
                self::renderForgotPasswordForm('', 'No se pudo enviar el email. Revisa la configuración SMTP del servidor.', '');
            }
            return;
        }

        // Modo dev: link visible en pantalla (sin SMTP).
        self::renderForgotPasswordForm($resetUrl, '', 'Hemos generado un enlace de recuperación.');
    }

    private static function renderForgotPasswordForm(string $resetUrl, string $error, string $info): void
    {
        $errorBlock = $error !== '' ? '<p class="err">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>' : '';
        $infoBlock = '';
        if ($info !== '') {
            $infoEsc = htmlspecialchars($info, ENT_QUOTES, 'UTF-8');
            $linkBlock = '';
            if ($resetUrl !== '') {
                // Modo dev (sin SMTP): exponemos el link en pantalla.
                $urlEsc = htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8');
                $linkBlock = <<<HTML
<p class="hint" style="margin-top:.5rem;">En Pruebas mostramos el enlace aquí (en producción se enviaría por email):</p>
<a href="{$urlEsc}" class="reset-link">{$urlEsc}</a>
HTML;
            }
            $infoBlock = <<<HTML
<div class="ok">
  <strong>{$infoEsc}</strong>
  {$linkBlock}
</div>
HTML;
        }
        self::idpHtmlHeaders();
        $styles = self::idpStyles();
        $brand = self::idpBrandHeader();
        echo <<<HTML
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Recuperar contraseña · Librería Gabi</title>
<link rel="icon" type="image/png" href="/assets/favicon.png">
{$styles}
<style>
  .ok {
    margin: 0.5rem 0 0;
    padding: 0.75rem 0.85rem;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    color: #065f46;
    border-radius: 8px;
    font-size: 0.85rem;
  }
  .reset-link {
    display: inline-block; margin-top: .6rem;
    word-break: break-all;
    font-family: ui-monospace, SFMono-Regular, Consolas, monospace;
    font-size: .76rem;
    color: #1a2d4e; text-decoration: underline;
  }
</style>
</head>
<body>
<div class="card">
  {$brand}
  <h1>Recuperar contraseña</h1>
  <p class="lead">Introduce el email de tu cuenta y te generaremos un enlace para restablecerla.</p>
  <form method="post" action="/idp/forgot-password">
    <label for="email">Email
      <input id="email" name="email" type="email" required autofocus autocomplete="email" placeholder="tu@email.com">
    </label>
    <button type="submit">Generar enlace</button>
    {$errorBlock}
    {$infoBlock}
  </form>
  <footer>
    Una sola cuenta para <span class="gold">todo el ecosistema</span>
  </footer>
</div>
</body>
</html>
HTML;
        exit;
    }

    /**
     * Recibe el token raw del link, busca su hash en BD, valida vigencia, y si
     * todo OK permite establecer la nueva contraseña.
     */
    public static function idpResetPassword(): void
    {
        $token = (string)($_GET['token'] ?? $_POST['token'] ?? '');
        if ($token === '' || !ctype_xdigit($token) || strlen($token) !== 64) {
            self::renderResetPasswordForm('', 'Enlace inválido o caducado.', false);
            return;
        }

        $tokenHash = hash('sha256', $token);
        $db = Database::connect();
        $stmt = $db->prepare(
            'SELECT id, user_id FROM password_reset_tokens
             WHERE token_hash = :hash AND used_at IS NULL AND expires_at > NOW()
             LIMIT 1'
        );
        $stmt->execute([':hash' => $tokenHash]);
        $row = $stmt->fetch();
        if (!$row) {
            self::renderResetPasswordForm('', 'Enlace inválido o caducado.', false);
            return;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            self::renderResetPasswordForm($token, '', true);
            return;
        }

        $newPassword = (string)($_POST['new_password'] ?? '');
        $newPasswordConfirm = (string)($_POST['new_password_confirm'] ?? '');

        if (strlen($newPassword) < 8) {
            self::renderResetPasswordForm($token, 'La contraseña debe tener al menos 8 caracteres.', true);
            return;
        }
        if ($newPassword !== $newPasswordConfirm) {
            self::renderResetPasswordForm($token, 'Las contraseñas no coinciden.', true);
            return;
        }

        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $db->beginTransaction();
        try {
            $up = $db->prepare('UPDATE users SET password = :pass WHERE id = :id');
            $up->execute([':pass' => $hash, ':id' => $row['user_id']]);
            $mark = $db->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE id = :id');
            $mark->execute([':id' => $row['id']]);
            $db->commit();
        } catch (\Throwable $e) {
            $db->rollBack();
            self::renderResetPasswordForm($token, 'No se pudo cambiar la contraseña.', true);
            return;
        }

        // Éxito: ofrecemos un botón a la web (libreria-taupe.vercel.app) en lugar
        // de a /idp/login directamente, porque /idp/login sin un OAuth request
        // pendiente aterriza al usuario en `/` del IdP (404 JSON). Desde allí
        // puede entrar a la web y disparar un /bff/start nuevo limpio.
        self::renderResetPasswordForm('', '', false, 'Contraseña actualizada. Pulsa el botón para volver a la tienda e iniciar sesión con tu nueva contraseña.');
    }

    private static function renderResetPasswordForm(string $token, string $error, bool $allowSubmit, string $success = ''): void
    {
        $tokenEsc = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
        $errorBlock = $error !== '' ? '<p class="err">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>' : '';
        // En éxito añadimos un CTA a la web de la librería. Si está vacío, no se muestra.
        $okCta = $success !== ''
            ? '<a class="btn-portal" href="https://libreria-taupe.vercel.app/">Volver a Librería Gabi</a>'
            : '';
        $okBlock = $success !== '' ? '<p class="ok">' . htmlspecialchars($success, ENT_QUOTES, 'UTF-8') . '</p>' . $okCta : '';
        $formBlock = '';
        if ($allowSubmit) {
            $formBlock = <<<HTML
<form method="post" action="/idp/reset-password">
  <input type="hidden" name="token" value="{$tokenEsc}">
  <label for="new_password">Nueva contraseña
    <input id="new_password" name="new_password" type="password" required minlength="8" autofocus autocomplete="new-password" placeholder="Mínimo 8 caracteres">
  </label>
  <label for="new_password_confirm">Repite la contraseña
    <input id="new_password_confirm" name="new_password_confirm" type="password" required minlength="8" autocomplete="new-password">
  </label>
  <button type="submit">Cambiar contraseña</button>
  {$errorBlock}
</form>
HTML;
        } else {
            $formBlock = $errorBlock;
        }
        self::idpHtmlHeaders();
        $styles = self::idpStyles();
        $brand = self::idpBrandHeader();
        echo <<<HTML
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Cambiar contraseña · Librería Gabi</title>
<link rel="icon" type="image/png" href="/assets/favicon.png">
{$styles}
<style>
  .ok {
    margin: 0.5rem 0 0;
    padding: 0.75rem 0.85rem;
    background: #ecfdf5;
    border: 1px solid #a7f3d0;
    color: #065f46;
    border-radius: 8px;
    font-size: 0.85rem;
  }
  .btn-portal {
    display: block;
    margin: 0.85rem 0 0;
    padding: 0.75rem 1rem;
    background: linear-gradient(135deg, #bd9b2c, #9b7e1e);
    color: #fff;
    text-align: center;
    text-decoration: none;
    border-radius: 10px;
    font-weight: 600;
    letter-spacing: 0.02em;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
  }
  .btn-portal:hover {
    transform: translateY(-1px);
    box-shadow: 0 6px 16px rgba(189, 155, 44, 0.4);
  }
</style>
</head>
<body>
<div class="card">
  {$brand}
  <h1>Cambiar contraseña</h1>
  <p class="lead">Introduce tu nueva contraseña.</p>
  {$okBlock}
  {$formBlock}
  <footer>
    Una sola cuenta para <span class="gold">todo el ecosistema</span>
  </footer>
</div>
</body>
</html>
HTML;
        exit;
    }
}
