<?php

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
    }
}
