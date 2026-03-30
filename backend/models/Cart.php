<?php

require_once __DIR__ . '/BaseModel.php';

class Cart extends BaseModel
{
    public function getActiveCart(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM carts WHERE user_id = :user_id AND status = \"active\" LIMIT 1');
        $stmt->execute(['user_id' => $userId]);
        $cart = $stmt->fetch();

        if (!$cart) {
            $stmt = $this->db->prepare('INSERT INTO carts (user_id, status, created_at) VALUES (:user_id, \"active\", NOW())');
            $stmt->execute(['user_id' => $userId]);
            $cart = ['id' => (int)$this->db->lastInsertId(), 'user_id' => $userId, 'status' => 'active'];
        }

        return $cart;
    }

    public function items(int $cartId): array
    {
        $stmt = $this->db->prepare('SELECT ci.*, p.name, p.price, p.stock FROM cart_items ci JOIN products p ON ci.product_id = p.id WHERE ci.cart_id = :cart_id');
        $stmt->execute(['cart_id' => $cartId]);
        return $stmt->fetchAll();
    }

    public function addItem(int $cartId, int $productId, int $quantity): void
    {
        $stmt = $this->db->prepare('SELECT id, quantity FROM cart_items WHERE cart_id = :cart_id AND product_id = :product_id');
        $stmt->execute(['cart_id' => $cartId, 'product_id' => $productId]);
        $item = $stmt->fetch();

        if ($item) {
            $stmt = $this->db->prepare('UPDATE cart_items SET quantity = :quantity WHERE id = :id');
            $stmt->execute(['quantity' => $item['quantity'] + $quantity, 'id' => $item['id']]);
        } else {
            $stmt = $this->db->prepare('INSERT INTO cart_items (cart_id, product_id, quantity) VALUES (:cart_id, :product_id, :quantity)');
            $stmt->execute(['cart_id' => $cartId, 'product_id' => $productId, 'quantity' => $quantity]);
        }
    }

    public function updateItem(int $cartId, int $productId, int $quantity): void
    {
        $stmt = $this->db->prepare('UPDATE cart_items SET quantity = :quantity WHERE cart_id = :cart_id AND product_id = :product_id');
        $stmt->execute(['quantity' => $quantity, 'cart_id' => $cartId, 'product_id' => $productId]);
    }

    public function removeItem(int $cartId, int $productId): void
    {
        $stmt = $this->db->prepare('DELETE FROM cart_items WHERE cart_id = :cart_id AND product_id = :product_id');
        $stmt->execute(['cart_id' => $cartId, 'product_id' => $productId]);
    }

    public function clear(int $cartId): void
    {
        $stmt = $this->db->prepare('DELETE FROM cart_items WHERE cart_id = :cart_id');
        $stmt->execute(['cart_id' => $cartId]);
    }
}
