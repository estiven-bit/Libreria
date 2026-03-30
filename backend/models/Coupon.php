<?php

require_once __DIR__ . '/BaseModel.php';

class Coupon extends BaseModel
{
    public function findActiveByCode(string $code): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM coupons WHERE code = :code AND active = 1 LIMIT 1');
        $stmt->execute(['code' => $code]);
        $coupon = $stmt->fetch();
        return $coupon ?: null;
    }
}
