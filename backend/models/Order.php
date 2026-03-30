<?php

require_once __DIR__ . '/BaseModel.php';

class Order extends BaseModel
{
    public function create(int $userId, string $status, float $total, string $paymentMethod): int
    {
        $stmt = $this->db->prepare('INSERT INTO orders (user_id, status, total_price, payment_method, created_at) VALUES (:user_id, :status, :total_price, :payment_method, NOW())');
        $stmt->execute([
            'user_id' => $userId,
            'status' => $status,
            'total_price' => $total,
            'payment_method' => $paymentMethod,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function addItem(int $orderId, int $productId, int $quantity, float $price): void
    {
        $stmt = $this->db->prepare('INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (:order_id, :product_id, :quantity, :price)');
        $stmt->execute([
            'order_id' => $orderId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'price' => $price,
        ]);
    }

    public function listByUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM orders WHERE user_id = :user_id ORDER BY created_at DESC');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }
}
