<?php

require_once __DIR__ . '/TelegramService.php';
require_once __DIR__ . '/../utils/Response.php';

class PaymentService
{
    private PDO $db;
    private array $config;

    public function __construct(PDO $db, array $config = [])
    {
        $this->db = $db;
        $this->config = $config;
    }

    public function createPaymentIntent(int $orderId, string $provider): array
    {
        $transactionId = 'txn_' . bin2hex(random_bytes(8));
        $stmt = $this->db->prepare(
            'INSERT INTO payments (order_id, payment_provider, payment_status, transaction_id) VALUES (:order_id, :payment_provider, :payment_status, :transaction_id)'
        );
        $stmt->execute([
            'order_id' => $orderId,
            'payment_provider' => $provider,
            'payment_status' => 'pending',
            'transaction_id' => $transactionId,
        ]);

        return ['transaction_id' => $transactionId, 'status' => 'pending'];
    }

    public function createStripeCheckoutSession(int $userId, int $orderId): array
    {
        $order = $this->findOrderForUser($userId, $orderId);
        if (!$order) {
            Response::json(['error' => 'Order not found'], 404);
        }

        $secretKey = trim((string)($this->config['stripe']['secret_key'] ?? ''));
        if ($secretKey === '') {
            Response::json(['error' => 'Stripe is not configured'], 500);
        }

        $frontendUrl = rtrim((string)($this->config['app']['frontend_url'] ?? 'http://localhost:5173'), '/');
        $currency = strtolower((string)($this->config['stripe']['currency'] ?? 'usd'));
        $amount = (int)round((float)$order['total_price'] * 100);

        $payload = http_build_query([
            'mode' => 'payment',
            'success_url' => $frontendUrl . '/pago-exitoso?session_id={CHECKOUT_SESSION_ID}&order_id=' . $orderId,
            'cancel_url' => $frontendUrl . '/checkout?order_id=' . $orderId . '&cancelled=1',
            'metadata[order_id]' => (string)$orderId,
            'metadata[user_id]' => (string)$userId,
            'line_items[0][price_data][currency]' => $currency,
            'line_items[0][price_data][product_data][name]' => 'Pedido #' . $orderId,
            'line_items[0][price_data][unit_amount]' => (string)$amount,
            'line_items[0][quantity]' => '1',
        ]);

        $response = $this->stripeRequest(
            'POST',
            'https://api.stripe.com/v1/checkout/sessions',
            $secretKey,
            $payload
        );

        $sessionId = trim((string)($response['id'] ?? ''));
        $paymentUrl = trim((string)($response['url'] ?? ''));
        if ($sessionId === '' || $paymentUrl === '') {
            Response::json(['error' => 'Stripe session could not be created'], 502);
        }

        $existing = $this->db->prepare(
            'SELECT id FROM payments WHERE order_id = :order_id AND payment_provider = :payment_provider LIMIT 1'
        );
        $existing->execute([
            'order_id' => $orderId,
            'payment_provider' => 'stripe',
        ]);
        $current = $existing->fetch();

        if ($current) {
            $update = $this->db->prepare(
                "UPDATE payments
                 SET transaction_id = :transaction_id, payment_status = 'pending'
                 WHERE id = :id"
            );
            $update->execute([
                'transaction_id' => $sessionId,
                'id' => (int)$current['id'],
            ]);
        } else {
            $insert = $this->db->prepare(
                'INSERT INTO payments (order_id, payment_provider, payment_status, transaction_id)
                 VALUES (:order_id, :payment_provider, :payment_status, :transaction_id)'
            );
            $insert->execute([
                'order_id' => $orderId,
                'payment_provider' => 'stripe',
                'payment_status' => 'pending',
                'transaction_id' => $sessionId,
            ]);
        }

        return [
            'session_id' => $sessionId,
            'payment_url' => $paymentUrl,
        ];
    }

    public function confirmStripeCheckoutSession(int $userId, int $orderId, string $sessionId): void
    {
        $order = $this->findOrderForUser($userId, $orderId);
        if (!$order) {
            Response::json(['error' => 'Order not found'], 404);
        }

        $secretKey = trim((string)($this->config['stripe']['secret_key'] ?? ''));
        if ($secretKey === '') {
            Response::json(['error' => 'Stripe is not configured'], 500);
        }

        $session = $this->stripeRequest(
            'GET',
            'https://api.stripe.com/v1/checkout/sessions/' . rawurlencode($sessionId),
            $secretKey
        );

        $sessionOrderId = (int)($session['metadata']['order_id'] ?? 0);
        $paymentStatus = (string)($session['payment_status'] ?? '');
        if ($sessionOrderId !== $orderId) {
            Response::json(['error' => 'Stripe session does not belong to the order'], 409);
        }
        if ($paymentStatus !== 'paid') {
            Response::json(['error' => 'Payment is not completed'], 409);
        }

        $paymentExists = $this->db->prepare(
            'SELECT id FROM payments WHERE order_id = :order_id AND transaction_id = :transaction_id LIMIT 1'
        );
        $paymentExists->execute([
            'order_id' => $orderId,
            'transaction_id' => $sessionId,
        ]);

        if (!$paymentExists->fetch()) {
            $insert = $this->db->prepare(
                'INSERT INTO payments (order_id, payment_provider, payment_status, transaction_id)
                 VALUES (:order_id, :payment_provider, :payment_status, :transaction_id)'
            );
            $insert->execute([
                'order_id' => $orderId,
                'payment_provider' => 'stripe',
                'payment_status' => 'pending',
                'transaction_id' => $sessionId,
            ]);
        }

        $this->confirmPayment($sessionId);
    }

    public function confirmPayment(string $transactionId): void
    {
        if ($transactionId === '') {
            return;
        }

        $stmt = $this->db->prepare(
            'SELECT id, order_id, payment_status FROM payments WHERE transaction_id = :transaction_id LIMIT 1'
        );
        $stmt->execute(['transaction_id' => $transactionId]);
        $payment = $stmt->fetch();
        if (!$payment) {
            return;
        }

        if (($payment['payment_status'] ?? '') === 'paid') {
            return;
        }

        $updPay = $this->db->prepare(
            "UPDATE payments SET payment_status = 'paid' WHERE transaction_id = :transaction_id"
        );
        $updPay->execute(['transaction_id' => $transactionId]);

        $orderId = (int)$payment['order_id'];

        $updOrder = $this->db->prepare(
            "UPDATE orders SET status = 'paid' WHERE id = :order_id AND status = 'pending'"
        );
        $updOrder->execute(['order_id' => $orderId]);

        if ($updOrder->rowCount() < 1) {
            return;
        }

        $info = $this->db->prepare(
            'SELECT o.id, o.total_price, u.name AS user_name
             FROM orders o
             INNER JOIN users u ON u.id = o.user_id
             WHERE o.id = :id
             LIMIT 1'
        );
        $info->execute(['id' => $orderId]);
        $row = $info->fetch();
        if ($row) {
            $tg = new TelegramService();
            $tg->notifyOrderPaid(
                (int)$row['id'],
                (string)$row['user_name'],
                (float)$row['total_price']
            );
        }
    }

    public function cancelPayment(string $transactionId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE payments SET payment_status = 'cancelled' WHERE transaction_id = :transaction_id"
        );
        $stmt->execute(['transaction_id' => $transactionId]);
    }

    private function findOrderForUser(int $userId, int $orderId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, user_id, total_price, status
             FROM orders
             WHERE id = :id AND user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute([
            'id' => $orderId,
            'user_id' => $userId,
        ]);

        $order = $stmt->fetch();
        return $order ?: null;
    }

    private function stripeRequest(string $method, string $url, string $secretKey, ?string $body = null): array
    {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $secretKey,
            'Content-Type: application/x-www-form-urlencoded',
        ]);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        if ($body !== null && $body !== '') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        $statusCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($raw === false || $raw === '') {
            error_log(sprintf('[%s] Stripe cURL error: %s', date('c'), $curlError));
            Response::json(['error' => 'Stripe network error'], 502);
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            Response::json(['error' => 'Invalid Stripe response'], 502);
        }

        if ($statusCode >= 400) {
            $message = (string)($decoded['error']['message'] ?? 'Stripe request failed');
            Response::json(['error' => $message], 502);
        }

        return $decoded;
    }
}
