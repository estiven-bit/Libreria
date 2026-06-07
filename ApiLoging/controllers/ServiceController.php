<?php
/**
 * Service-to-Service controller — endpoints `/auth/admin/*` autenticados con
 * Service JWT firmado con SERVICE_JWT_SECRET (HS256). Los backends del
 * ecosistema (Inmobiliaria, etc.) hablan aquí cuando necesitan resolver
 * datos de usuarios sin tener una sesión OIDC user (typically JOIN
 * cross-service en listados admin).
 *
 * No reemplaza al BFF: el BFF sirve al frontend con cookie OIDC; este
 * controlador sirve a OTROS BACKENDS server-to-server con bearer JWT.
 *
 * Endpoints expuestos:
 *   GET  /auth/admin/users/by-id?id=N        → {user: {...}} | 404
 *   GET  /auth/admin/users/by-email?email=X  → {user: {...}} | 404
 *   POST /auth/admin/users-batch             → {users: [...]}
 */

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class ServiceController
{
    /**
     * Valida el Service JWT del Authorization header. Si falla, responde 401
     * y termina la request. Si va bien, devuelve el payload decodificado.
     */
    private static function requireServiceAuth(): array
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $lower = [];
        foreach ($headers as $k => $v) $lower[strtolower((string)$k)] = $v;
        $auth = (string)($lower['authorization'] ?? '');

        if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
            Response::json(['error' => 'missing_bearer'], 401);
        }
        $token = trim($m[1]);

        $secret = self::serviceSecret();
        try {
            $payload = (array) JWT::decode($token, new Key($secret, 'HS256'));
        } catch (\Throwable $e) {
            error_log('[service_auth] JWT inválido: ' . $e->getMessage());
            Response::json(['error' => 'invalid_token'], 401);
        }

        // Defensa básica: el caller debe identificarse como service.
        $role = strtolower((string)($payload['role'] ?? ''));
        if ($role !== 'service') {
            Response::json(['error' => 'not_a_service_token'], 403);
        }

        return $payload;
    }

    private static function serviceSecret(): string
    {
        $value = getenv('SERVICE_JWT_SECRET') ?: '';
        if ($value !== '') return $value;

        $envFile = __DIR__ . '/../.env';
        if (is_file($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                if (preg_match('/^SERVICE_JWT_SECRET\s*=\s*(.+)$/', trim($line), $m)) {
                    return trim($m[1]);
                }
            }
        }

        error_log('[service_auth] SERVICE_JWT_SECRET no configurado en ApiLoging');
        Response::json(['error' => 'service_secret_not_configured'], 500);
    }

    /**
     * Forma estable que devuelven todos los endpoints: el receptor solo
     * necesita id/email/name/role. Otros campos se añaden si están presentes
     * pero no se garantizan.
     */
    private static function shapeUser(array $row): array
    {
        return [
            'id'         => (int)$row['id'],
            'email'      => (string)($row['email'] ?? ''),
            'name'       => (string)($row['name'] ?? ''),
            'username'   => (string)($row['username'] ?? ''),
            'role'       => strtolower((string)($row['role'] ?? 'user')),
            'phone'      => (string)($row['phone'] ?? ''),
            'first_name' => (string)($row['first_name'] ?? ''),
            'last_name'  => (string)($row['last_name'] ?? ''),
            'created_at' => isset($row['created_at']) ? (string)$row['created_at'] : null,
            'banned_at'  => isset($row['banned_at']) ? (string)$row['banned_at'] : null,
        ];
    }

    public static function findUserById(): void
    {
        self::requireServiceAuth();
        $id = (int)($_GET['id'] ?? 0);
        if ($id <= 0) Response::json(['error' => 'invalid_id'], 400);

        $stmt = Database::connect()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        if (!$row) Response::json(['error' => 'not_found'], 404);

        Response::json(['user' => self::shapeUser($row)]);
    }

    public static function findUserByEmail(): void
    {
        self::requireServiceAuth();
        $email = trim((string)($_GET['email'] ?? ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::json(['error' => 'invalid_email'], 400);
        }

        $stmt = Database::connect()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        if (!$row) Response::json(['error' => 'not_found'], 404);

        Response::json(['user' => self::shapeUser($row)]);
    }

    /**
     * Batch lookup: { ids:[], emails:[] } → { users:[...] } sin garantía
     * de orden. Caller indexa por id/email según necesite.
     */
    public static function findManyUsers(): void
    {
        self::requireServiceAuth();
        $body = json_decode(file_get_contents('php://input') ?: '', true) ?: [];
        $ids    = array_values(array_unique(array_map('intval', $body['ids']    ?? [])));
        $emails = array_values(array_unique(array_filter(array_map(fn($e) => trim((string)$e), $body['emails'] ?? []))));

        if (empty($ids) && empty($emails)) {
            Response::json(['users' => []]);
        }

        $where = [];
        $params = [];

        if (!empty($ids)) {
            $idsPositive = array_values(array_filter($ids, fn($n) => $n > 0));
            if (!empty($idsPositive)) {
                $ph = [];
                foreach ($idsPositive as $i => $v) {
                    $key = ':id_' . $i;
                    $ph[] = $key;
                    $params[$key] = $v;
                }
                $where[] = 'id IN (' . implode(',', $ph) . ')';
            }
        }

        if (!empty($emails)) {
            $ph = [];
            foreach ($emails as $i => $v) {
                $key = ':em_' . $i;
                $ph[] = $key;
                $params[$key] = $v;
            }
            $where[] = 'email IN (' . implode(',', $ph) . ')';
        }

        if (empty($where)) Response::json(['users' => []]);

        $sql = 'SELECT * FROM users WHERE ' . implode(' OR ', $where);
        $stmt = Database::connect()->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $users = array_map([self::class, 'shapeUser'], $rows);
        Response::json(['users' => $users]);
    }

    /**
     * GET /auth/admin/users — devuelve TODO el censo de usuarios (sin
     * paginación todavía: el cliente "Gestión de usuarios" del admin de
     * inmobiliaria lista a mano todo el grupo). Excluye el campo password
     * vía shapeUser().
     */
    public static function listAllUsers(): void
    {
        self::requireServiceAuth();
        $rows = Database::connect()->query('SELECT * FROM users ORDER BY id ASC')->fetchAll();
        $users = array_map([self::class, 'shapeUser'], $rows);
        Response::json(['users' => $users]);
    }

    /**
     * POST /auth/admin/verify-password — confirma que la contraseña dada
     * coincide con el hash del usuario `user_id`. Usado por el admin de
     * inmobiliaria para confirmar acciones sensibles (delete propiedad,
     * cambio de estado, etc.) sin tener que abrir sesión IdP separada.
     *
     * Devuelve {valid: bool}. NO emite 401 propio (el cliente ya cuenta
     * intentos contra su propio rate-limit).
     */
    public static function verifyUserPassword(): void
    {
        self::requireServiceAuth();
        $body = json_decode(file_get_contents('php://input') ?: '', true) ?: [];
        $userId   = (int)($body['user_id'] ?? 0);
        $password = (string)($body['password'] ?? '');
        if ($userId <= 0 || $password === '') {
            Response::json(['error' => 'invalid_input'], 422);
        }

        $stmt = Database::connect()->prepare('SELECT password FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch();

        $valid = $row && password_verify($password, (string)$row['password']);
        Response::json(['valid' => (bool)$valid]);
    }

    /**
     * POST /auth/admin/update-role — actualiza users.role. El caller
     * (inmobiliaria admin panel) ya valida que quien dispara la acción es
     * admin via su propio middleware; aquí solo confiamos en el Service JWT.
     */
    public static function updateUserRole(): void
    {
        self::requireServiceAuth();
        $body = json_decode(file_get_contents('php://input') ?: '', true) ?: [];
        $userId = (int)($body['user_id'] ?? 0);
        $role   = strtolower(trim((string)($body['role'] ?? '')));

        $allowed = ['admin', 'real', 'user', 'empleado'];
        if ($userId <= 0 || !in_array($role, $allowed, true)) {
            Response::json(['error' => 'invalid_input'], 422);
        }

        $stmt = Database::connect()->prepare('UPDATE users SET role = :r WHERE id = :id');
        $stmt->execute([':r' => $role, ':id' => $userId]);
        if ($stmt->rowCount() === 0) {
            Response::json(['error' => 'not_found'], 404);
        }

        Response::json(['updated' => true, 'user_id' => $userId, 'role' => $role]);
    }
}
