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
}
