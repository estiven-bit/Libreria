<?php

require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Validator.php';
require_once __DIR__ . '/../models/Cart.php';
require_once __DIR__ . '/../models/Product.php';

class CartController
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function get(int $userId): void
    {
        $cartModel = new Cart($this->db);
        $cart = $cartModel->getActiveCart($userId);
        $items = $cartModel->items((int)$cart['id']);
        Response::json(['cart' => $cart, 'items' => $items]);
    }

    public function add(int $userId, array $data): void
    {
        $missing = Validator::required($data, ['product_id', 'quantity']);
        if ($missing) {
            Response::json(['error' => 'Missing fields', 'fields' => $missing], 422);
        }

        $quantity = (int)$data['quantity'];
        if ($quantity < 1) {
            Response::json(['error' => 'Minimum quantity is 1'], 422);
        }

        $productModel = new Product($this->db);
        $product = $productModel->find((int)$data['product_id']);
        if (!$product || $product['stock'] < $quantity) {
            Response::json(['error' => 'Insufficient stock'], 409);
        }

        $cartModel = new Cart($this->db);
        $cart = $cartModel->getActiveCart($userId);
        $cartModel->addItem((int)$cart['id'], (int)$data['product_id'], $quantity);
        Response::json(['message' => 'Item added']);
    }

    public function update(int $userId, array $data): void
    {
        $missing = Validator::required($data, ['product_id', 'quantity']);
        if ($missing) {
            Response::json(['error' => 'Missing fields', 'fields' => $missing], 422);
        }

        $quantity = (int)$data['quantity'];
        if ($quantity < 1) {
            Response::json(['error' => 'Minimum quantity is 1'], 422);
        }

        $cartModel = new Cart($this->db);
        $cart = $cartModel->getActiveCart($userId);
        $cartModel->updateItem((int)$cart['id'], (int)$data['product_id'], $quantity);
        Response::json(['message' => 'Item updated']);
    }

    public function remove(int $userId, array $data): void
    {
        $missing = Validator::required($data, ['product_id']);
        if ($missing) {
            Response::json(['error' => 'Missing fields', 'fields' => $missing], 422);
        }

        $cartModel = new Cart($this->db);
        $cart = $cartModel->getActiveCart($userId);
        $cartModel->removeItem((int)$cart['id'], (int)$data['product_id']);
        Response::json(['message' => 'Item removed']);
    }
}
