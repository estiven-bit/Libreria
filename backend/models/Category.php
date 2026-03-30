<?php

require_once __DIR__ . '/BaseModel.php';


class Category extends BaseModel
{
    public function list(): array
    {
        $stmt = $this->db->query('SELECT * FROM categories ORDER BY name ASC');
        return $stmt->fetchAll();
    }
}
