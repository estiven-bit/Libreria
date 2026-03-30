<?php

class EmailService
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function sendOrderConfirmation(string $toEmail, int $orderId): void
    {
        $subject = 'Confirmacion de pedido #' . $orderId;
        $message = 'Gracias por tu compra en Libreria Gabi. Tu pedido ha sido recibido.';
        $headers = 'From: ' . $this->config['from_name'] . ' <' . $this->config['from_email'] . '>';
        @mail($toEmail, $subject, $message, $headers);
    }
}
