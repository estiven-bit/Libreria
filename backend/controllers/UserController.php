<?php

require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Address.php';
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../utils/Sanitizer.php';

class UserController
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function profile(int $userId): void
    {
        $userModel = new User($this->db);
        $user = $userModel->findById($userId);
        if (!$user) {
            Response::json(['error' => 'User not found'], 404);
        }
        unset($user['password_hash']);
        Response::json(['data' => $user]);
    }

    public function addresses(int $userId): void
    {
        $model = new Address($this->db);
        Response::json(['data' => $model->listByUser($userId)]);
    }

    public function addAddress(int $userId, array $data): void
    {
        $model = new Address($this->db);
        $id = $model->create($userId, [
            'country' => Sanitizer::string($data['country'] ?? ''),
            'city' => Sanitizer::string($data['city'] ?? ''),
            'postal_code' => Sanitizer::string($data['postal_code'] ?? ''),
            'address_line' => Sanitizer::string($data['address_line'] ?? ''),
        ]);
        Response::json(['message' => 'Address added', 'id' => $id], 201);
    }

    public function orders(int $userId): void
    {
        $orderModel = new Order($this->db);
        $orders = $orderModel->listByUser($userId);

        $stmt = $this->db->prepare('
            SELECT oi.*, p.name as product_name,
                (SELECT pi.id FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.id ASC LIMIT 1) AS primary_image_id
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            WHERE oi.order_id = :order_id
        ');

        foreach ($orders as &$order) {
            $stmt->execute(['order_id' => (int)$order['id']]);
            $order['items'] = $stmt->fetchAll() ?: [];
        }
        unset($order);

        Response::json(['data' => $orders]);
    }

    public function deleteAddress(int $userId, int $addressId): void
    {
        $model = new Address($this->db);
        $deleted = $model->deleteByUser($userId, $addressId);
        if ($deleted) {
            Response::json(['message' => 'Dirección eliminada']);
        } else {
            Response::json(['error' => 'Dirección no encontrada o no autorizada'], 404);
        }
    }
}
