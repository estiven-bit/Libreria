<?php

require_once __DIR__ . '/BaseModel.php';


class Category extends BaseModel
{
    public function list(): array
    {
        $stmt = $this->db->query('SELECT * FROM categories ORDER BY name ASC');
        return $stmt->fetchAll();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare('INSERT INTO categories (name, parent_id) VALUES (:name, :parent_id)');
        $stmt->execute([
            'name' => $data['name'],
            'parent_id' => !empty($data['parent_id']) ? (int)$data['parent_id'] : null,
        ]);
        return (int)$this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare('UPDATE categories SET name = :name, parent_id = :parent_id WHERE id = :id');
        $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'parent_id' => !empty($data['parent_id']) ? (int)$data['parent_id'] : null,
        ]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->db->prepare('DELETE FROM categories WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
