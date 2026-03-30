<?php
// 1. Iniciamos el buffer para evitar que cualquier espacio o log rompa el JSON
ob_start();

require_once __DIR__ . '/../middlewares/CorsMiddleware.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../middlewares/RateLimitMiddleware.php';
require_once __DIR__ . '/../middlewares/JsonBodyMiddleware.php';
require_once __DIR__ . '/../middlewares/AuthMiddleware.php';
require_once __DIR__ . '/../middlewares/AdminMiddleware.php';
require_once __DIR__ . '/../middlewares/CsrfMiddleware.php';

require_once __DIR__ . '/../controllers/AuthController.php';
require_once __DIR__ . '/../controllers/ProductController.php';
require_once __DIR__ . '/../controllers/CategoryController.php';
require_once __DIR__ . '/../controllers/CartController.php';
require_once __DIR__ . '/../controllers/OrderController.php';
require_once __DIR__ . '/../controllers/PaymentController.php';
require_once __DIR__ . '/../controllers/UserController.php';
require_once __DIR__ . '/../controllers/AdminController.php';
require_once __DIR__ . '/../controllers/CouponController.php';
require_once __DIR__ . '/../controllers/UploadController.php';
require_once __DIR__ . '/../services/UploadService.php';

$config = require __DIR__ . '/../config/config.php';
$db = require __DIR__ . '/../config/database.php';

// CORS y rate limiting
CorsMiddleware::handle($config['app']);
RateLimitMiddleware::handle($config['app']);

$logger = new Logger($config['app']['logs_path']);
$logger->info('request', ['method' => $_SERVER['REQUEST_METHOD'], 'uri' => $_SERVER['REQUEST_URI']]);

$routes = require __DIR__ . '/../routes/api.php';
$method = $_SERVER['REQUEST_METHOD'];

// --- CORRECCIÓN DE RUTA ---
$uri = $_SERVER['PATH_INFO'] ?? '';

if (empty($uri) || $uri === '/') {
    $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $uri = str_replace('/libreria_gabi/backend/public/index.php', '', $uri);
}

// Aseguramos que empiece con barra para que coincida con api.php (ej: /categories)
$uri = '/' . ltrim($uri, '/'); 

$body = JsonBodyMiddleware::parse();

// Limpiamos el buffer antes de cualquier posible salida JSON para evitar errores de Syntax
if (ob_get_length()) ob_clean();

// Función de matching
function matchRoute(array $route, string $method, string $uri): ?array
{
    if ($route[0] !== $method) {
        return null;
    }

    $pattern = preg_replace('#\\{[a-zA-Z_]+\\}#', '([a-zA-Z0-9_-]+)', $route[1]);
    $pattern = '#^' . $pattern . '$#';

    if (preg_match($pattern, $uri, $matches)) {
        array_shift($matches);
        return $matches;
    }

    return null;
}

$matched = null;
$params = [];

foreach ($routes as $route) {
    $params = matchRoute($route, $method, $uri);
    if ($params !== null) {
        $matched = $route;
        break;
    }
}

if (!$matched) {
    Response::json(['error' => 'Not found', 'debug_uri' => $uri], 404);
    exit;
}

[$_, $_path, $handler] = $matched;

if ($handler === 'health') {
    Response::json(['status' => 'ok']);
    exit;
}

// Reglas de acceso
$user = null;
if (str_starts_with($handler, 'admin.') || str_starts_with($handler, 'cart.') || str_starts_with($handler, 'orders.') || str_starts_with($handler, 'user.')) {
    $user = AuthMiddleware::requireAuth($config['app']);
}

if (str_starts_with($handler, 'admin.')) {
    AdminMiddleware::requireAdmin($user);
}

// CSRF
if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true) && $user) {
    if (!CsrfMiddleware::verify()) {
        Response::json(['error' => 'Invalid CSRF token'], 403);
        exit;
    }
}

// Ejecución de controladores
switch ($handler) {
    case 'auth.register':
        (new AuthController($db, $config['app']))->register($body);
        break;
    case 'auth.activate':
        (new AuthController($db, $config['app']))->activate($_GET); // Usamos $_GET porque el token viene en la URL
        break;
    case 'auth.login':
        (new AuthController($db, $config['app']))->login($body);
        break;
    case 'categories.list':
        (new CategoryController($db))->list();
        break;
    case 'products.list':
        (new ProductController($db))->list($_GET);
        break;
    case 'products.show':
        (new ProductController($db))->show((int)$params[0]);
        break;
    case 'products.create':
        (new ProductController($db))->create($body);
        break;
    case 'products.update':
        (new ProductController($db))->update((int)$params[0], $body);
        break;
    case 'products.delete':
        (new ProductController($db))->delete((int)$params[0]);
        break;
    case 'cart.get':
        (new CartController($db))->get((int)$user['sub']);
        break;
    case 'cart.add':
        (new CartController($db))->add((int)$user['sub'], $body);
        break;
    case 'cart.update':
        (new CartController($db))->update((int)$user['sub'], $body);
        break;
    case 'cart.remove':
        (new CartController($db))->remove((int)$user['sub'], $body);
        break;
    case 'orders.list':
        (new OrderController($db, $config))->list((int)$user['sub']);
        break;
    case 'orders.create':
        (new OrderController($db, $config))->create((int)$user['sub'], $body);
        break;
    case 'orders.cancel':
        (new OrderController($db, $config))->cancel((int)$user['sub'], (int)$params[0]);
        break;
    case 'payment.create':
        (new PaymentController($db))->create($body);
        break;
    case 'payment.confirm':
        (new PaymentController($db))->confirm($body);
        break;
    case 'payment.webhook':
        (new PaymentController($db))->webhook($body);
        break;
    case 'user.profile':
        (new UserController($db))->profile((int)$user['sub']);
        break;
    case 'user.addresses':
        (new UserController($db))->addresses((int)$user['sub']);
        break;
    case 'user.addAddress':
        (new UserController($db))->addAddress((int)$user['sub'], $body);
        break;
    case 'admin.stats':
        (new AdminController($db))->stats();
        break;
    case 'admin.users':
        (new AdminController($db))->users();
        break;
    case 'coupons.list':
        (new CouponController($db))->list();
        break;
    case 'coupons.create':
        (new CouponController($db))->create($body);
        break;
    case 'admin.upload':
        $service = new UploadService($config['app']['uploads_path']);
        (new UploadController($service))->upload();
        break;
    default:
        Response::json(['error' => 'Not found'], 404);
}