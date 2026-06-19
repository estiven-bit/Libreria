<?php

require_once __DIR__ . '/BaseModel.php';

class Notification extends BaseModel
{
    public function create(int $userId, ?int $orderId, string $message): void
    {
        $stmt = $this->db->prepare('INSERT INTO user_notifications (user_id, order_id, message, is_read, created_at) VALUES (:user_id, :order_id, :message, 0, NOW())');
        $stmt->execute([
            'user_id' => $userId,
            'order_id' => $orderId,
            'message' => $message
        ]);
    }

    public function listByUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM user_notifications WHERE user_id = :user_id ORDER BY created_at DESC LIMIT 50');
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function markAllAsRead(int $userId): void
    {
        $stmt = $this->db->prepare('UPDATE user_notifications SET is_read = 1 WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
    }
}
