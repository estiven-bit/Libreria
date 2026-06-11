<?php

/**
 * Punto de entrada principal (Front Controller) para ApiLoging (Pruebas).
 *
 * Migrado a OIDC/BFF puro: NO hay endpoints /auth/* del JWT antiguo.
 * Todo el auth pasa por league/oauth2-server (Authorization Code + PKCE)
 * con un BFF dedicado que envuelve los tokens en una sesión HttpOnly.
 */

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

if (!function_exists('env')) {
    function env(string $key, string $default = ''): string {
        $v = getenv($key);
        if ($v !== false && $v !== '') return $v;
        if (isset($_ENV[$key]) && $_ENV[$key] !== '') return $_ENV[$key];
        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') return $_SERVER[$key];
        return $default;
    }
}

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/Env.php';
require_once __DIR__ . '/config/Database.php';
require_once __DIR__ . '/utils/Response.php';
require_once __DIR__ . '/utils/Security.php';
require_once __DIR__ . '/utils/DeferredTasks.php';
require_once __DIR__ . '/utils/DatabaseSessionHandler.php';
require_once __DIR__ . '/services/MailService.php';
require_once __DIR__ . '/services/GeoLocationService.php';
require_once __DIR__ . '/services/NotionService.php';
require_once __DIR__ . '/controllers/OAuthController.php';
require_once __DIR__ . '/controllers/BffController.php';
require_once __DIR__ . '/controllers/ServiceController.php';

Env::load(__DIR__ . '/.env');

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

// Keep-alive ligero: sin sesión ni BD (Vercel cron cada 5 min).
if ($uri === '/bff/health' && $method === 'GET') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode(['ok' => true, 'ts' => time()]);
    exit;
}

// Registramos el manejador de sesiones en base de datos para persistencia en entorno Serverless
session_set_save_handler(new DatabaseSessionHandler(), true);

Security::sendSecurityHeaders();
Security::enforceProductionTransport();
Security::bootstrapCors();

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Defensa CSRF para endpoints /bff/* que mutan estado. Aplica solo a
// POST/PUT/PATCH/DELETE bajo /bff/*: rechaza la petición si el Origin/Referer
// no está en REDIRECT_ALLOWED_ORIGINS. Necesario porque la cookie bff_session
// va con SameSite=None (los SPAs viven en eTLD+1 distintos del BFF), así que
// el navegador la enviaría en peticiones cross-site originadas por terceros
// sin esta validación. Ver utils/Security.php::enforceBffCsrf().
Security::enforceBffCsrf();

// ---- Assets estáticos (favicon, logo del IdP) ----
// Whitelist por regex para evitar path traversal: solo nombres simples
// con extensiones permitidas. Sirve archivos desde storage/assets/.
if ($method === 'GET' && preg_match('#^/assets/([a-zA-Z0-9._-]+\.(png|svg|jpg|jpeg|webp|ico|mp4))$#', $uri, $m)) {
    $path = __DIR__ . '/storage/assets/' . $m[1];
    if (is_file($path)) {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = ['png' => 'image/png', 'svg' => 'image/svg+xml', 'jpg' => 'image/jpeg',
                 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'ico' => 'image/x-icon',
                 'mp4' => 'video/mp4'][$ext] ?? 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Cache-Control: public, max-age=3600');
        readfile($path);
        exit;
    }
    http_response_code(404);
    exit;
}

// ---- Endpoints OIDC (IdP) ----
if ($uri === '/.well-known/openid-configuration' && $method === 'GET') OAuthController::discovery();
if ($uri === '/.well-known/jwks.json' && $method === 'GET') OAuthController::jwks();
if ($uri === '/oauth/authorize' && $method === 'GET') OAuthController::authorize();
if ($uri === '/oauth/token' && $method === 'POST') OAuthController::token();
if ($uri === '/oauth/userinfo' && $method === 'GET') OAuthController::userinfo();
if ($uri === '/idp/login' && in_array($method, ['GET', 'POST'], true)) OAuthController::idpLogin();
if ($uri === '/idp/register' && in_array($method, ['GET', 'POST'], true)) OAuthController::idpRegister();
if ($uri === '/idp/verify-email' && $method === 'GET') OAuthController::idpVerifyEmail();
if ($uri === '/idp/confirm-email-change' && $method === 'GET') OAuthController::idpConfirmEmailChange();
if ($uri === '/idp/email-change-deny' && $method === 'GET') OAuthController::idpEmailChangeDeny();
if ($uri === '/idp/settings' && in_array($method, ['GET', 'POST'], true)) OAuthController::idpSettings();
if ($uri === '/idp/revoke-session' && $method === 'POST') OAuthController::idpRevokeSession();
if ($uri === '/idp/login-confirm' && $method === 'GET') OAuthController::idpLoginConfirm();
if ($uri === '/idp/forgot-password' && in_array($method, ['GET', 'POST'], true)) OAuthController::idpForgotPassword();
if ($uri === '/idp/reset-password' && in_array($method, ['GET', 'POST'], true)) OAuthController::idpResetPassword();
if ($uri === '/idp/logout' && in_array($method, ['GET', 'POST'], true)) OAuthController::idpLogout();
if ($uri === '/idp/logout-one' && $method === 'GET') OAuthController::idpLogoutOne();

// ---- BFF endpoints (cada frontend los llama via Vite proxy) ----
if ($uri === '/bff/health' && $method === 'GET') BffController::health();
if ($uri === '/bff/start' && $method === 'GET') BffController::start();
if ($uri === '/bff/callback' && $method === 'GET') BffController::callback();
if ($uri === '/bff/me' && $method === 'GET') BffController::me();
if ($uri === '/bff/logout' && $method === 'POST') BffController::logout();
if ($uri === '/bff/accounts' && $method === 'GET') BffController::accounts();
if ($uri === '/bff/switch' && $method === 'POST') BffController::switchAccount();
if ($uri === '/bff/logout-one' && $method === 'POST') BffController::logoutOne();

// ---- BFF admin (solo accesible si la cuenta activa tiene role='admin') ----
if ($uri === '/bff/admin/users' && $method === 'GET') BffController::adminListUsers();
if ($uri === '/bff/admin/update-role' && $method === 'POST') BffController::adminUpdateRole();
if ($uri === '/bff/admin/set-ban' && $method === 'POST') BffController::adminSetBan();
if ($uri === '/bff/admin/force-logout' && $method === 'POST') BffController::adminForceLogout();
if ($uri === '/bff/admin/sync-notion' && $method === 'POST') BffController::adminSyncNotion();

// ---- BFF proxy a backends por-app: /bff/<app>/<path> → http://localhost:<port>/<path>.php
// El frontend de cada app llama aquí en vez de hablar directamente con su BACKEND;
// así el access_token nunca sale del servidor y el BACKEND solo confía en peticiones
// firmadas con BFF_SERVICE_SECRET.
if (preg_match('#^/bff/(libreriagabi)/(.+)$#', $uri, $m)) {
    BffController::proxyToAppBackend($m[1], $m[2], $method);
}

// ---- S2S (Service-to-Service): otros backends del ecosistema piden datos
// de usuarios para hacer JOINs cross-service. Auth: Service JWT firmado con
// SERVICE_JWT_SECRET; NO usa sesión OIDC.
if ($uri === '/auth/admin/users'          && $method === 'GET')  ServiceController::listAllUsers();
if ($uri === '/auth/admin/users/by-id'    && $method === 'GET')  ServiceController::findUserById();
if ($uri === '/auth/admin/users/by-email' && $method === 'GET')  ServiceController::findUserByEmail();
if ($uri === '/auth/admin/users-batch'    && $method === 'POST') ServiceController::findManyUsers();
if ($uri === '/auth/admin/verify-password'&& $method === 'POST') ServiceController::verifyUserPassword();
if ($uri === '/auth/admin/update-role'    && $method === 'POST') ServiceController::updateUserRole();

Response::json(['error' => 'not found'], 404);
