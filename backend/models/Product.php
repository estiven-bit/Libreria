<?php

require_once __DIR__ . '/BaseModel.php';

class Product extends BaseModel
{
    public function list(array $filters = []): array
    {
        $sql = 'SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE 1=1';
        $params = [];

        if (!empty($filters['category_id'])) {
            $sql .= ' AND p.category_id = :category_id';
            $params['category_id'] = $filters['category_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= ' AND (p.name LIKE :search OR p.description LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }

        $sql .= ' ORDER BY p.created_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $product = $stmt->fetch();
        return $product ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('INSERT INTO products (name, description, price, stock, category_id, created_at) VALUES (:name, :description, :price, :stock, :category_id, NOW())');
        $stmt->execute($data);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare('UPDATE products SET name = :name, description = :description, price = :price, stock = :stock, category_id = :category_id WHERE id = :id');
        $data['id'] = $id;
        $stmt->execute($data);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM products WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
