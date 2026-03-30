<?php

require_once __DIR__ . '/BaseModel.php';

class Address extends BaseModel
{
    public function listByUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM addresses WHERE user_id = :user_id ORDER BY id DESC');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function create(int $userId, array $data): int
    {
        $stmt = $this->db->prepare('INSERT INTO addresses (user_id, country, city, postal_code, address_line) VALUES (:user_id, :country, :city, :postal_code, :address_line)');
        $stmt->execute([
            'user_id' => $userId,
            'country' => $data['country'],
            'city' => $data['city'],
            'postal_code' => $data['postal_code'],
            'address_line' => $data['address_line'],
        ]);
        return (int)$this->db->lastInsertId();
    }
}
