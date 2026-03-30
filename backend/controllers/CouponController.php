<?php

require_once __DIR__ . '/../utils/Response.php';

class CouponController
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function list(): void
    {
        $stmt = $this->db->query('SELECT * FROM coupons ORDER BY id DESC');
        Response::json(['data' => $stmt->fetchAll()]);
    }

    public function create(array $data): void
    {
        $stmt = $this->db->prepare('INSERT INTO coupons (code, discount_percentage, active) VALUES (:code, :discount_percentage, :active)');
        $stmt->execute([
            'code' => $data['code'] ?? '',
            'discount_percentage' => (int)($data['discount_percentage'] ?? 0),
            'active' => (int)($data['active'] ?? 1),
        ]);
        Response::json(['message' => 'Coupon created'], 201);
    }
}
