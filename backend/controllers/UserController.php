<?php

require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../models/Address.php';
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
}
