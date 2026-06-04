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

        if (!empty($data['coupon_code'])) {
            $couponModel = new Coupon($this->db);
            $coupon = $couponModel->findActiveByCode($data['coupon_code']);
            if ($coupon) {
                $total = $total - ($total * ($coupon['discount_percentage'] / 100));
            }
        }

        $orderModel = new Order($this->db);
        $orderId = $orderModel->create($userId, 'pending', (float)$total, $data['payment_method'] ?? 'cash_on_delivery');

        foreach ($items as $item) {
            $orderModel->addItem($orderId, (int)$item['product_id'], (int)$item['quantity'], (float)$item['price']);
        }

        $stockService = new StockService($this->db);
        foreach ($items as $item) {
            $stockService->reduceStock((int)$item['product_id'], (int)$item['quantity'], 'Order #' . $orderId);
        }

        $cartModel->clear((int)$cart['id']);

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

            $message = "📦 <b>NUEVO PEDIDO RECIBIDO</b>\n\n" .
                       "Pedido: <b>#{$orderId}</b>\n" .
                       "Cliente: <b>{$userInfo['name']}</b>\n" .
                       "Total: <b>\$" . number_format($total, 2) . "</b>\n" .
                       "Método de pago: {$methodText}\n" .
                       "Estado: {$statusText}\n";

            $tg->sendMessage($message);

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
        Response::json(['message' => 'Order cancelled']);
    }
}
