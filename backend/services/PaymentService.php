<?php

class PaymentService
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function createPaymentIntent(int $orderId, string $provider): array
    {
        $transactionId = 'txn_' . bin2hex(random_bytes(8));
        $stmt = $this->db->prepare('INSERT INTO payments (order_id, payment_provider, payment_status, transaction_id) VALUES (:order_id, :provider, :status, :transaction_id)');
        $stmt->execute([
            'order_id' => $orderId,
            'provider' => $provider,
            'status' => 'pending',
            'transaction_id' => $transactionId,
        ]);

        return ['transaction_id' => $transactionId, 'status' => 'pending'];
    }

    public function confirmPayment(string $transactionId): void
    {
        $stmt = $this->db->prepare('UPDATE payments SET payment_status = \"paid\" WHERE transaction_id = :transaction_id');
        $stmt->execute(['transaction_id' => $transactionId]);
    }

    public function cancelPayment(string $transactionId): void
    {
        $stmt = $this->db->prepare('UPDATE payments SET payment_status = \"cancelled\" WHERE transaction_id = :transaction_id');
        $stmt->execute(['transaction_id' => $transactionId]);
    }
}
