<?php

require_once __DIR__ . '/BaseModel.php';

class Log extends BaseModel
{
    public function create(string $event): int
    {
        $stmt = $this->db->prepare('INSERT INTO logs (event, created_at) VALUES (:event, NOW())');
        $stmt->execute(['event' => $event]);
        return (int)$this->db->lastInsertId();
    }

    public function list(int $limit = 200): array
    {
        $limit = max(1, min(500, $limit));
        $stmt = $this->db->prepare("SELECT id, event, created_at FROM logs ORDER BY created_at DESC LIMIT $limit");
        $stmt->execute();
        return $stmt->fetchAll();
    }
}

