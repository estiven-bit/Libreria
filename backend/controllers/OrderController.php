<?php

require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Cart.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../services/StockService.php';
require_once __DIR__ . '/../services/EmailService.php';
require_once __DIR__ . '/../models/Coupon.php';
require_once __DIR__ . '/../services/PdfService.php';
require_once __DIR__ . '/../services/TelegramService.php';

class OrderController
{
    private PDO $db;
    private array $config;

    public function __construct(PDO $db, array $config)
    {
        $this->db = $db;
        $this->config = $config;
    }

    public function list(int $userId): void
    {
        $orderModel = new Order($this->db);
        Response::json(['data' => $orderModel->listByUser($userId)]);
    }

    public function create(int $userId, array $data): void
    {
        $cartModel = new Cart($this->db);
        $cart = $cartModel->getActiveCart($userId);
        $items = $cartModel->items((int)$cart['id']);
        if (!$items) {
            Response::json(['error' => 'Cart is empty'], 409);
        }

        $total = 0;
        foreach ($items as $item) {
            if ($item['stock'] < $item['quantity']) {
                Response::json(['error' => 'Insufficient stock for product ' . $item['product_id']], 409);
            }
            $total += $item['price'] * $item['quantity'];
        }

        $couponCode = null;
        $discountAmount = 0.00;
        if (!empty($data['coupon_code'])) {
            $couponModel = new Coupon($this->db);
            $coupon = $couponModel->findActiveByCode($data['coupon_code']);
            if ($coupon) {
                $couponCode = $coupon['code'];
                $discountAmount = $total * ($coupon['discount_percentage'] / 100);
                $total = $total - $discountAmount;
            }
        }

        $orderModel = new Order($this->db);
        $orderId = $orderModel->create($userId, 'pending', (float)$total, $data['payment_method'] ?? 'cash_on_delivery', $couponCode, (float)$discountAmount);

        foreach ($items as $item) {
            $orderModel->addItem($orderId, (int)$item['product_id'], (int)$item['quantity'], (float)$item['price']);
        }

        $cartModel->clear((int)$cart['id']);

        // Insert notification
        try {
            require_once __DIR__ . '/../models/Notification.php';
            $bookDesc = Notification::getOrderBooksDescription($this->db, $orderId);
            (new Notification($this->db))->create($userId, $orderId, 'Tu pedido de ' . $bookDesc . ' ha sido recibido.');
        } catch (\Throwable $e) {
            // Ignore
        }

        $email = new EmailService($this->config['mail']);
        $email->sendOrderConfirmation($data['user_email'] ?? '', $orderId);

        // --- NOTIFICACIÓN A TELEGRAM CON PDF ---
        try {
            // 1. Obtener datos de dirección y usuario
            $addrStmt = $this->db->prepare('SELECT * FROM addresses WHERE id = :id LIMIT 1');
            $addrStmt->execute(['id' => (int)($data['address_id'] ?? 0)]);
            $addressInfo = $addrStmt->fetch() ?: [
                'country' => 'No provisto',
                'city' => 'No provisto',
                'postal_code' => 'No provisto',
                'address_line' => 'No provisto'
            ];

            $usrStmt = $this->db->prepare('SELECT name, email, phone FROM users WHERE id = :id LIMIT 1');
            $usrStmt->execute(['id' => $userId]);
            $userInfo = $usrStmt->fetch() ?: [
                'name' => 'Cliente',
                'email' => $data['user_email'] ?? '',
                'phone' => 'No provisto'
            ];

            // Obtener datos del pedido que acabamos de guardar (para el PDF)
            $orderRow = [
                'id' => $orderId,
                'total_price' => $total,
                'payment_method' => $data['payment_method'] ?? 'cash_on_delivery',
                'created_at' => date('Y-m-d H:i:s')
            ];

            // 2. Generar el PDF
            $pdfService = new PdfService();
            $pdfPath = $pdfService->generateOrderPdf($orderRow, $userInfo, $addressInfo, $items);

            // 3. Enviar mensaje de texto a Telegram
            $tg = new TelegramService();
            
            $methodText = ($orderRow['payment_method'] === 'card_online') 
                ? '💳 Tarjeta (Pago online)' 
                : '💵 Pago al recibir (Contra reembolso)';
            $statusText = ($orderRow['payment_method'] === 'card_online') 
                ? '<i>Pendiente de confirmación de pasarela</i>' 
                : '<b>Pendiente de pago al recibir</b>';

            $secretPrep = hash_hmac('sha256', $orderId . '-preparing', env('JWT_SECRET', 'change_this_secret'));
            $secretReady = hash_hmac('sha256', $orderId . '-ready', env('JWT_SECRET', 'change_this_secret'));
            $secretDeliv = hash_hmac('sha256', $orderId . '-delivered', env('JWT_SECRET', 'change_this_secret'));
            $secretCancel = hash_hmac('sha256', $orderId . '-cancelled', env('JWT_SECRET', 'change_this_secret'));

            $publicUrl = env('APP_PUBLIC_URL', '');
            if (empty($publicUrl) || (str_contains($publicUrl, 'localhost') && isset($_SERVER['HTTP_HOST']) && !str_contains($_SERVER['HTTP_HOST'], 'localhost'))) {
                $protocol = 'https';
                $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
                if (isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
                    $host = $_SERVER['HTTP_X_FORWARDED_HOST'];
                }
                $publicUrl = $protocol . '://' . $host;
            } else {
                if (!str_contains($publicUrl, 'localhost') && !str_contains($publicUrl, '127.0.0.1')) {
                    $publicUrl = str_replace('http://', 'https://', $publicUrl);
                }
            }
            if (empty($publicUrl)) {
                $publicUrl = 'http://localhost/libreria_gabi/backend/public';
            }

            $urlPrep = rtrim($publicUrl, '/') . "/api/orders/telegram-update?order_id={$orderId}&status=preparing&token={$secretPrep}";
            $urlReady = rtrim($publicUrl, '/') . "/api/orders/telegram-update?order_id={$orderId}&status=ready&token={$secretReady}";
            $urlDeliv = rtrim($publicUrl, '/') . "/api/orders/telegram-update?order_id={$orderId}&status=delivered&token={$secretDeliv}";
            $urlCancel = rtrim($publicUrl, '/') . "/api/orders/telegram-update?order_id={$orderId}&status=cancelled&token={$secretCancel}";

            $customerNameEscaped = htmlspecialchars($userInfo['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $customerEmailEscaped = htmlspecialchars($userInfo['email'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $customerPhoneEscaped = htmlspecialchars($userInfo['phone'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            $itemsText = "";
            foreach ($items as $item) {
                $itemNameEscaped = htmlspecialchars($item['name'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
                $itemsText .= "- {$itemNameEscaped} (x{$item['quantity']}) - \$" . number_format($item['price'] * $item['quantity'], 2) . "\n";
            }

            $addrText = "Dirección: " . htmlspecialchars($addressInfo['address_line'] . ", " . $addressInfo['city'] . " (" . $addressInfo['postal_code'] . "), " . $addressInfo['country'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            $message = "📦 <b>NUEVO PEDIDO RECIBIDO</b>\n\n" .
                       "Pedido: <b>#{$orderId}</b>\n" .
                       "Cliente: <b>{$customerNameEscaped}</b> ({$customerEmailEscaped})\n" .
                       "Teléfono: {$customerPhoneEscaped}\n" .
                       "Método de pago: {$methodText}\n" .
                       "Estado: {$statusText}\n\n" .
                       "<b>Libros:</b>\n" . $itemsText . "\n" .
                       "{$addrText}\n\n" .
                       "Total: <b>\$" . number_format($total, 2) . "</b>";

            $keyboard = [
                'inline_keyboard' => [
                    [
                        [
                            'text' => '⚙️ Preparando',
                            'url' => $urlPrep
                        ],
                        [
                            'text' => '📦 Listo para Entregar',
                            'url' => $urlReady
                        ]
                    ],
                    [
                        [
                            'text' => '✅ Entregado y Cobrado',
                            'url' => $urlDeliv
                        ],
                        [
                            'text' => '❌ Cancelar Pedido',
                            'url' => $urlCancel
                        ]
                    ]
                ]
            ];

            $tg->sendMessage($message, $keyboard);

            // 4. Enviar el PDF
            $caption = "Etiqueta y Ticket del Pedido #{$orderId}";
            $tg->sendDocument($pdfPath, $caption);

            // 5. Eliminar PDF temporal
            if (file_exists($pdfPath)) {
                unlink($pdfPath);
            }
        } catch (\Throwable $e) {
            error_log('Error enviando pedido a Telegram: ' . $e->getMessage());
        }

        Response::json(['status' => 'success', 'order_id' => $orderId], 201);
    }

    public function cancel(int $userId, int $orderId): void
    {
        $stmt = $this->db->prepare("UPDATE orders SET status = 'cancelled' WHERE id = :id AND user_id = :user_id AND status IN ('pending', 'paid')");
        $stmt->execute(['id' => $orderId, 'user_id' => $userId]);
        
        if ($stmt->rowCount() > 0) {
            try {
                require_once __DIR__ . '/../models/Notification.php';
                (new Notification($this->db))->create($userId, $orderId, 'Tu pedido #' . $orderId . ' ha sido cancelado.');
            } catch (\Throwable $e) {
                // Ignore
            }
        }
        
        Response::json(['message' => 'Order cancelled']);
    }

    public function telegramDeliver(int $orderId, string $token): void
    {
        $expected = hash_hmac('sha256', (string)$orderId, env('JWT_SECRET', 'change_this_secret'));
        if (!hash_equals($expected, $token)) {
            $this->renderHtmlFeedback("Acceso Denegado", "El token de autorización no es válido o ha expirado.", false);
            exit;
        }

        $stmt = $this->db->prepare('SELECT id, status FROM orders WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            $this->renderHtmlFeedback("Pedido No Encontrado", "El pedido con ID #{$orderId} no existe en la base de datos.", false);
            exit;
        }

        if ($order['status'] === 'delivered') {
            $this->renderHtmlFeedback("Pedido Ya Entregado", "El pedido #{$orderId} ya estaba marcado como entregado.", true);
            exit;
        }

        $up = $this->db->prepare("UPDATE orders SET status = 'delivered' WHERE id = :id");
        $up->execute(['id' => $orderId]);

        // Get user ID of the order to notify
        try {
            $uStmt = $this->db->prepare('SELECT user_id FROM orders WHERE id = :id LIMIT 1');
            $uStmt->execute(['id' => $orderId]);
            $uRow = $uStmt->fetch();
            if ($uRow) {
                require_once __DIR__ . '/../models/Notification.php';
                $bookDesc = Notification::getOrderBooksDescription($this->db, $orderId);
                (new Notification($this->db))->create((int)$uRow['user_id'], $orderId, 'Tu pedido de ' . $bookDesc . ' ha sido entregado.');
            }
        } catch (\Throwable $e) {
            // Ignore
        }

        $stockService = new StockService($this->db);
        $stockService->reduceStockForOrder($orderId);

        try {
            $logStmt = $this->db->prepare('INSERT INTO logs (event, created_at) VALUES (:event, NOW())');
            $logStmt->execute(['event' => "Pedido #{$orderId} marcado como entregado vía Telegram"]);
        } catch (\Exception $e) {
            // Ignorar
        }

        $this->renderHtmlFeedback("¡Pedido Entregado!", "El pedido #{$orderId} ha sido marcado como entregado con éxito.", true);
        exit;
    }

    public function telegramUpdateStatus(int $orderId, string $status, string $token): void
    {
        $expected = hash_hmac('sha256', $orderId . '-' . $status, env('JWT_SECRET', 'change_this_secret'));
        if (!hash_equals($expected, $token)) {
            $this->renderHtmlFeedback("Acceso Denegado", "El token de autorización no es válido o ha expirado.", false);
            exit;
        }

        $stmt = $this->db->prepare('SELECT id, status, user_id FROM orders WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            $this->renderHtmlFeedback("Pedido No Encontrado", "El pedido con ID #{$orderId} no existe en la base de datos.", false);
            exit;
        }

        if ($order['status'] === $status) {
            $this->renderHtmlFeedback("Estado ya actualizado", "El pedido #{$orderId} ya se encuentra en estado '{$status}'.", true);
            exit;
        }

        $up = $this->db->prepare("UPDATE orders SET status = :status WHERE id = :id");
        $up->execute(['status' => $status, 'id' => $orderId]);

        if (in_array($status, ['paid', 'delivered'], true)) {
            $stockService = new StockService($this->db);
            $stockService->reduceStockForOrder($orderId);
        }

        // Send user notification
        try {
            require_once __DIR__ . '/../models/Notification.php';
            $bookDesc = Notification::getOrderBooksDescription($this->db, $orderId);
            
            $statusMap = [
                'pending' => 'Tu pedido de ' . $bookDesc . ' ha sido recibido.',
                'paid' => 'Tu pedido de ' . $bookDesc . ' ha sido pagado con éxito.',
                'cancelled' => 'Tu pedido de ' . $bookDesc . ' ha sido cancelado.',
                'preparing' => 'Tu pedido de ' . $bookDesc . ' se está preparando.',
                'ready' => 'Tu pedido de ' . $bookDesc . ' está listo para entregar.',
                'shipped' => 'Tu pedido de ' . $bookDesc . ' ha sido enviado.',
                'delivered' => 'Tu pedido de ' . $bookDesc . ' ha sido entregado.'
            ];
            
            $msg = $statusMap[$status] ?? ('El estado de tu pedido de ' . $bookDesc . ' ha cambiado a ' . $status);
            (new Notification($this->db))->create((int)$order['user_id'], $orderId, $msg);
        } catch (\Throwable $e) {
            // Ignore
        }

        try {
            $logStmt = $this->db->prepare('INSERT INTO logs (event, created_at) VALUES (:event, NOW())');
            $logStmt->execute(['event' => "Pedido #{$orderId} cambiado a '{$status}' vía Telegram"]);
        } catch (\Exception $e) {
            // Ignore
        }

        $titleMap = [
            'preparing' => '¡Pedido en Preparación!',
            'ready' => '¡Pedido Listo para Entregar!',
            'delivered' => '¡Pedido Entregado!',
            'cancelled' => '¡Pedido Cancelado!'
        ];
        $msgMap = [
            'preparing' => 'El pedido #'.$orderId.' ha sido marcado en preparación con éxito.',
            'ready' => 'El pedido #'.$orderId.' ha sido marcado como listo para entregar.',
            'delivered' => 'El pedido #'.$orderId.' ha sido marcado como entregado y cobrado con éxito.',
            'cancelled' => 'El pedido #'.$orderId.' ha sido cancelado con éxito.'
        ];
        $title = $titleMap[$status] ?? '¡Estado Actualizado!';
        $message = $msgMap[$status] ?? 'El pedido #'.$orderId.' ha cambiado de estado con éxito.';

        $isSuccess = ($status !== 'cancelled');
        $this->renderHtmlFeedback($title, $message, $isSuccess);
        exit;
    }

    private function renderHtmlFeedback(string $title, string $message, bool $success): void
    {
        $color = $success ? '#10b981' : '#ef4444';
        $icon = $success ? '✓' : '✕';
        
        echo <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title} | Librería Gabi</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Segoe UI', -apple-system, sans-serif;
            background: radial-gradient(circle at top, #fff1c2, #fff9e6);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .card {
            background: rgba(255, 255, 255, 0.75);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            max-width: 420px;
            width: 90%;
        }
        .icon {
            font-size: 40px;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: {$color};
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        h1 {
            font-size: 24px;
            color: #1e293b;
            margin-bottom: 15px;
        }
        p {
            font-size: 16px;
            color: #64748b;
            line-height: 1.5;
            margin-bottom: 30px;
        }
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #ff9f43, #ff6b6b);
            color: white;
            padding: 12px 25px;
            border-radius: 10px;
            text-decoration: none;
            font-weight: bold;
            box-shadow: 0 4px 12px rgba(255, 107, 107, 0.2);
            transition: 0.2s;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(255, 107, 107, 0.3);
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">{$icon}</div>
        <h1>{$title}</h1>
        <p>{$message}</p>
        <a href="/" class="btn">Ir al inicio</a>
    </div>
</body>
</html>
HTML;
    }
}
