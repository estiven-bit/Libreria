<?php

require_once __DIR__ . '/../utils/Response.php';

class AdminController
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function stats(): void
    {
        $orders = $this->db->query('SELECT COUNT(*) as total FROM orders')->fetch();
        $users = $this->db->query('SELECT COUNT(*) as total FROM users')->fetch();
        $products = $this->db->query('SELECT COUNT(*) as total FROM products')->fetch();

        Response::json([
            'data' => [
                'orders' => (int)$orders['total'],
                'users' => (int)$users['total'],
                'products' => (int)$products['total'],
            ],
        ]);
    }

    public function users(): void
    {
        $stmt = $this->db->query('SELECT id, name, email, role, created_at FROM users ORDER BY created_at DESC');
        Response::json(['data' => $stmt->fetchAll()]);
    }
}
