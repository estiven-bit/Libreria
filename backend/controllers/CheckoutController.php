<?php

require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/PaymentService.php';

class CheckoutController
{
    private PDO $db;
    private array $config;

    public function __construct(PDO $db, array $config)
    {
        $this->db = $db;
        $this->config = $config;
    }

    public function createSession(int $userId, array $data): void
    {
        $orderId = (int)($data['order_id'] ?? 0);
        if ($orderId < 1) {
            Response::json(['error' => 'order_id is required'], 422);
        }

        $service = new PaymentService($this->db, $this->config);
        $result = $service->createStripeCheckoutSession($userId, $orderId);

        Response::json([
            'status' => 'success',
            'order_id' => $orderId,
            'payment_url' => $result['payment_url'],
            'session_id' => $result['session_id'],
        ], 201);
    }

    public function confirmSession(int $userId, array $data): void
    {
        $sessionId = trim((string)($data['session_id'] ?? ''));
        $orderId = (int)($data['order_id'] ?? 0);
        if ($sessionId === '' || $orderId < 1) {
            Response::json(['error' => 'session_id and order_id are required'], 422);
        }

        $service = new PaymentService($this->db, $this->config);
        $service->confirmStripeCheckoutSession($userId, $orderId, $sessionId);

        Response::json([
            'status' => 'success',
            'order_id' => $orderId,
        ]);
    }
}
