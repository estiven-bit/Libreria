<?php

require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Cart.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../services/StockService.php';
require_once __DIR__ . '/../services/EmailService.php';
require_once __DIR__ . '/../models/Coupon.php';

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

        Response::json(['message' => 'Order created', 'order_id' => $orderId], 201);
    }

    public function cancel(int $userId, int $orderId): void
    {
        $stmt = $this->db->prepare('UPDATE orders SET status = \"cancelled\" WHERE id = :id AND user_id = :user_id AND status IN (\"pending\", \"paid\")');
        $stmt->execute(['id' => $orderId, 'user_id' => $userId]);
        Response::json(['message' => 'Order cancelled']);
    }
}
