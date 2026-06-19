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
        $stmt = $this->db->prepare('UPDATE products SET stock = stock - :qty WHERE id = :id AND stock >= :qty_limit');
        $stmt->execute(['qty' => $quantity, 'id' => $productId, 'qty_limit' => $quantity]);

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

    public function reduceStockForOrder(int $orderId): void
    {
        $reason = 'Order #' . $orderId;

        // Comprobar si ya se restó stock para este pedido en stock_history
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM stock_history WHERE reason = :reason');
        $stmt->execute(['reason' => $reason]);
        if ((int)$stmt->fetchColumn() > 0) {
            return;
        }

        // Obtener los productos del pedido
        $itemsStmt = $this->db->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = :order_id');
        $itemsStmt->execute(['order_id' => $orderId]);
        $items = $itemsStmt->fetchAll();

        // Restar stock
        foreach ($items as $item) {
            $this->reduceStock((int)$item['product_id'], (int)$item['quantity'], $reason);
        }
    }
}
