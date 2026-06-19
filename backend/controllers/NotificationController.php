<?php

require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../models/Notification.php';

class NotificationController
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function list(int $userId): void
    {
        $model = new Notification($this->db);
        Response::json(['data' => $model->listByUser($userId)]);
    }

    public function read(int $userId): void
    {
        $model = new Notification($this->db);
        $model->markAllAsRead($userId);
        Response::json(['message' => 'Notifications marked as read']);
    }
}
