<?php

require_once __DIR__ . '/TelegramService.php';

class StockService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function reduceStock(int $productId, int $quantity, string $reason): void
    {
        $stmt = $this->db->prepare('UPDATE products SET stock = stock - :qty WHERE id = :id AND stock >= :qty');
        $stmt->execute(['qty' => $quantity, 'id' => $productId]);

        $stmt = $this->db->prepare('INSERT INTO stock_history (product_id, quantity_change, reason, created_at) VALUES (:product_id, :quantity_change, :reason, NOW())');
        $stmt->execute([
            'product_id' => $productId,
            'quantity_change' => -$quantity,
            'reason' => $reason,
        ]);

        $check = $this->db->prepare('SELECT name, stock FROM products WHERE id = :id LIMIT 1');
        $check->execute(['id' => $productId]);
        $row = $check->fetch();
        if ($row && (int)$row['stock'] < 5) {
            (new TelegramService())->notifyLowStock((string)$row['name']);
        }
    }
}
