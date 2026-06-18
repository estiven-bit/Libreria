<?php

require_once __DIR__ . '/BaseModel.php';

class Product extends BaseModel
{
    public function list(array $filters = []): array
    {
        $sql = 'SELECT p.*, c.name AS category_name,
            (SELECT pi.id FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.id ASC LIMIT 1) AS primary_image_id
            FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE 1=1';
        $params = [];

        if (!empty($filters['category_id'])) {
            $sql .= ' AND p.category_id = :category_id';
            $params['category_id'] = $filters['category_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= ' AND (p.name LIKE :search OR p.description LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['min_price'])) {
            $sql .= ' AND p.price >= :min_price';
            $params['min_price'] = (float)$filters['min_price'];
        }

        if (!empty($filters['max_price'])) {
            $sql .= ' AND p.price <= :max_price';
            $params['max_price'] = (float)$filters['max_price'];
        }

        if (isset($filters['in_stock']) && ($filters['in_stock'] === 'true' || $filters['in_stock'] === '1' || $filters['in_stock'] === 1)) {
            $sql .= ' AND p.stock > 0';
        }

        $sort = $filters['sort'] ?? '';
        switch ($sort) {
            case 'price_asc':
                $sql .= ' ORDER BY p.price ASC';
                break;
            case 'price_desc':
                $sql .= ' ORDER BY p.price DESC';
                break;
            case 'name_asc':
                $sql .= ' ORDER BY p.name ASC';
                break;
            case 'name_desc':
                $sql .= ' ORDER BY p.name DESC';
                break;
            default:
                $sql .= ' ORDER BY p.created_at DESC';
                break;
        }

        if (isset($filters['limit']) && (int)$filters['limit'] > 0) {
            $limit = (int)$filters['limit'];
            $page = isset($filters['page']) ? (int)$filters['page'] : 1;
            $offset = ($page - 1) * $limit;
            $sql .= ' LIMIT ' . $limit . ' OFFSET ' . $offset;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function count(array $filters = []): int
    {
        $sql = 'SELECT COUNT(*) FROM products p LEFT JOIN categories c ON p.category_id = c.id WHERE 1=1';
        $params = [];

        if (!empty($filters['category_id'])) {
            $sql .= ' AND p.category_id = :category_id';
            $params['category_id'] = $filters['category_id'];
        }

        if (!empty($filters['search'])) {
            $sql .= ' AND (p.name LIKE :search OR p.description LIKE :search)';
            $params['search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['min_price'])) {
            $sql .= ' AND p.price >= :min_price';
            $params['min_price'] = (float)$filters['min_price'];
        }

        if (!empty($filters['max_price'])) {
            $sql .= ' AND p.price <= :max_price';
            $params['max_price'] = (float)$filters['max_price'];
        }

        if (isset($filters['in_stock']) && ($filters['in_stock'] === 'true' || $filters['in_stock'] === '1' || $filters['in_stock'] === 1)) {
            $sql .= ' AND p.stock > 0';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM products WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $id]);
        $product = $stmt->fetch();
        return $product ?: null;
    }

    /**
     * Producto con categoría e imágenes (para detalle).
     */
    public function findWithDetails(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, c.name AS category_name, c.parent_id AS category_parent_id
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.id = :id
             LIMIT 1'
        );
        $stmt->execute(['id' => $id]);
        $product = $stmt->fetch();
        if (!$product) {
            return null;
        }

        $img = $this->db->prepare('SELECT id FROM product_images WHERE product_id = :pid ORDER BY id ASC');
        $img->execute(['pid' => $id]);
        $product['images'] = $img->fetchAll();
        $product['category'] = [
            'id' => $product['category_id'] !== null ? (int)$product['category_id'] : null,
            'name' => $product['category_name'] ?? null,
            'parent_id' => isset($product['category_parent_id']) ? $product['category_parent_id'] : null,
        ];

        return $product;
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
