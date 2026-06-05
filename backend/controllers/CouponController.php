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

    public function update(int $id, array $data): void
    {
        if (!array_key_exists('active', $data)) {
            Response::json(['error' => 'active required'], 422);
        }
        $active = (int)$data['active'] ? 1 : 0;
        $stmt = $this->db->prepare('UPDATE coupons SET active = :a WHERE id = :id');
        $stmt->execute(['a' => $active, 'id' => $id]);
        Response::json(['message' => 'Cupón actualizado']);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM coupons WHERE id = :id');
        $stmt->execute(['id' => $id]);
        Response::json(['message' => 'Cupón eliminado']);
    }

    public function listActive(): void
    {
        $stmt = $this->db->query('SELECT * FROM coupons WHERE active = 1 ORDER BY id DESC');
        Response::json(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
}
