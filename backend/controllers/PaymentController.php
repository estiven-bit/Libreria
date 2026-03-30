<?php

require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../services/PaymentService.php';
require_once __DIR__ . '/../middlewares/CsrfMiddleware.php';

class PaymentController
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function create(array $data): void
    {
        if (!CsrfMiddleware::verify()) {
            Response::json(['error' => 'Invalid CSRF token'], 403);
        }

        $service = new PaymentService($this->db);
        $result = $service->createPaymentIntent((int)$data['order_id'], $data['provider'] ?? 'stripe');
        Response::json(['data' => $result], 201);
    }

    public function confirm(array $data): void
    {
        $service = new PaymentService($this->db);
        $service->confirmPayment($data['transaction_id'] ?? '');
        Response::json(['message' => 'Payment confirmed']);
    }

    public function webhook(array $data): void
    {
        $service = new PaymentService($this->db);
        if (($data['event'] ?? '') === 'payment.cancelled') {
            $service->cancelPayment($data['transaction_id'] ?? '');
        }
        Response::json(['message' => 'Webhook received']);
    }
}
