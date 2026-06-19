<?php

require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/Log.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../services/TelegramService.php';

class AdminController
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function stats(): void
    {
        $orders = $this->db->query('SELECT COUNT(*) as total FROM orders')->fetch();
        $users = $this->db->query('SELECT COUNT(*) as total FROM users')->fetch();
        $products = $this->db->query('SELECT COUNT(*) as total FROM products')->fetch();

        Response::json([
            'data' => [
                'orders' => (int)$orders['total'],
                'users' => (int)$users['total'],
                'products' => (int)$products['total'],
            ],
        ]);
    }

    public function users(): void
    {
        $stmt = $this->db->query('SELECT id, name, email, role, is_active, created_at FROM users ORDER BY created_at DESC');
        Response::json(['data' => $stmt->fetchAll()]);
    }

    public function updateUser(int $userId, array $data): void
    {
        if (!array_key_exists('is_active', $data)) {
            Response::json(['error' => 'is_active required'], 422);
        }
        $active = (int)$data['is_active'] ? 1 : 0;
        $userModel = new User($this->db);
        $userModel->setIsActive($userId, $active);
        Response::json(['message' => 'Usuario actualizado']);
    }

    public function deleteUser(int $userId): void
    {
        $stmt = $this->db->prepare('SELECT id, role FROM users WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $userId]);
        $row = $stmt->fetch();
        if (!$row) {
            Response::json(['error' => 'User not found'], 404);
        }
        if (($row['role'] ?? '') === 'ADMINISTRADOR') {
            Response::json(['error' => 'No se puede eliminar un administrador'], 403);
        }
        (new User($this->db))->deleteById($userId);
        Response::json(['message' => 'Usuario eliminado']);
    }

    public function orders(): void
    {
        $stmt = $this->db->query(
            'SELECT o.id, o.status, o.total_price, o.payment_method, o.created_at, o.coupon_code, o.discount_amount,
                    u.id AS user_id, u.name AS user_name, u.email AS user_email
             FROM orders o
             INNER JOIN users u ON u.id = o.user_id
             ORDER BY o.created_at DESC'
        );
        $orders = $stmt->fetchAll() ?: [];

        $itemStmt = $this->db->prepare('
            SELECT oi.*, p.name as product_name,
                (SELECT pi.id FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.id ASC LIMIT 1) AS primary_image_id
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = :order_id
        ');

        foreach ($orders as &$order) {
            $itemStmt->execute(['order_id' => (int)$order['id']]);
            $order['items'] = $itemStmt->fetchAll() ?: [];
        }
        unset($order);

        Response::json(['data' => $orders]);
    }

    public function updateOrderStatus(int $orderId, array $data): void
    {
        $status = (string)($data['status'] ?? '');
        $allowed = ['pending', 'paid', 'cancelled', 'shipped', 'delivered', 'preparing', 'ready'];
        if (!in_array($status, $allowed, true)) {
            Response::json(['error' => 'Invalid status'], 422);
        }

        $prevStmt = $this->db->prepare('SELECT status FROM orders WHERE id = :id LIMIT 1');
        $prevStmt->execute(['id' => $orderId]);
        $prevRow = $prevStmt->fetch();
        $previous = $prevRow ? (string)$prevRow['status'] : '';

        $stmt = $this->db->prepare('UPDATE orders SET status = :status WHERE id = :id');
        $stmt->execute(['status' => $status, 'id' => $orderId]);

        if (in_array($status, ['paid', 'delivered'], true)) {
            require_once __DIR__ . '/../services/StockService.php';
            (new StockService($this->db))->reduceStockForOrder($orderId);
        }

        // Notify user about status change
        $ordInfo = $this->db->prepare('SELECT user_id FROM orders WHERE id = :id LIMIT 1');
        $ordInfo->execute(['id' => $orderId]);
        $ordRow = $ordInfo->fetch();
        if ($ordRow) {
            $userId = (int)$ordRow['user_id'];
            require_once __DIR__ . '/../models/Notification.php';
            $notificationModel = new Notification($this->db);
            
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
            $notificationModel->create($userId, $orderId, $msg);
        }

        if ($status === 'paid' && $previous !== 'paid') {
            $info = $this->db->prepare(
                'SELECT o.id, o.total_price, u.name AS user_name
                 FROM orders o
                 INNER JOIN users u ON u.id = o.user_id
                 WHERE o.id = :id
                 LIMIT 1'
            );
            $info->execute(['id' => $orderId]);
            $row = $info->fetch();
            if ($row) {
                (new TelegramService())->notifyOrderPaid(
                    (int)$row['id'],
                    (string)$row['user_name'],
                    (float)$row['total_price']
                );
            }
        }

        Response::json(['message' => 'Order status updated']);
    }

    public function logs(array $query): void
    {
        $limit = isset($query['limit']) ? (int)$query['limit'] : 200;
        $model = new Log($this->db);
        Response::json(['data' => $model->list($limit)]);
    }
}
