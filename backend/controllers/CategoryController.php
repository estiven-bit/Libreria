<?php

require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../models/Category.php';

class CategoryController
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function list(): void
    {
        $model = new Category($this->db);
        Response::json(['data' => $model->list()]);
    }

    public function create(array $body): void
    {
        if (empty($body['name'])) {
            Response::json(['error' => 'El nombre de la categoría es obligatorio.'], 422);
            return;
        }
        $model = new Category($this->db);
        $id = $model->create($body);
        Response::json(['message' => 'Categoría creada', 'id' => $id], 201);
    }

    public function update(int $id, array $body): void
    {
        if (empty($body['name'])) {
            Response::json(['error' => 'El nombre de la categoría es obligatorio.'], 422);
            return;
        }
        $model = new Category($this->db);
        $model->update($id, $body);
        Response::json(['message' => 'Categoría actualizada']);
    }

    public function delete(int $id): void
    {
        try {
            $model = new Category($this->db);
            $model->delete($id);
            Response::json(['message' => 'Categoría eliminada']);
        } catch (\PDOException $e) {
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'a foreign key constraint fails')) {
                Response::json(['error' => 'No se puede eliminar la categoría porque hay productos asociados a ella.'], 409);
            } else {
                Response::json(['error' => 'Error al eliminar la categoría: ' . $e->getMessage()], 500);
            }
        }
    }
}
