<?php

require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../models/Product.php';
require_once __DIR__ . '/../utils/Sanitizer.php';

class ProductController
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function list(array $filters): void
    {
        $model = new Product($this->db);
        $products = $model->list($filters);
        Response::json(['data' => $products]);
    }

    public function show(int $id): void
    {
        $model = new Product($this->db);
        $product = $model->find($id);
        if (!$product) {
            Response::json(['error' => 'Product not found'], 404);
        }
        Response::json(['data' => $product]);
    }

    public function create(array $data): void
    {
        $model = new Product($this->db);
        $id = $model->create([
            'name' => Sanitizer::string($data['name'] ?? ''),
            'description' => Sanitizer::string($data['description'] ?? ''),
            'price' => (float)($data['price'] ?? 0),
            'stock' => (int)($data['stock'] ?? 0),
            'category_id' => (int)($data['category_id'] ?? 0),
        ]);
        Response::json(['message' => 'Product created', 'id' => $id], 201);
    }

    public function update(int $id, array $data): void
    {
        $model = new Product($this->db);
        $model->update($id, [
            'name' => Sanitizer::string($data['name'] ?? ''),
            'description' => Sanitizer::string($data['description'] ?? ''),
            'price' => (float)($data['price'] ?? 0),
            'stock' => (int)($data['stock'] ?? 0),
            'category_id' => (int)($data['category_id'] ?? 0),
        ]);
        Response::json(['message' => 'Product updated']);
    }

    public function delete(int $id): void
    {
        $model = new Product($this->db);
        $model->delete($id);
        Response::json(['message' => 'Product deleted']);
    }
}
