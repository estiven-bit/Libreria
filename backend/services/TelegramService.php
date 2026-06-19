<?php

/**
 * Notificaciones a Telegram. Token y chat desde $_ENV / getenv (backend/.env).
 */
class TelegramService
{
    private string $token;
    private string $chatId;

    public function __construct(?string $token = null, ?string $chatId = null)
    {
        $this->token = $token ?? (string)(($_ENV['TELEGRAM_BOT_TOKEN'] ?? getenv('TELEGRAM_BOT_TOKEN')) ?: '');
        $this->chatId = $chatId ?? (string)(($_ENV['TELEGRAM_CHAT_ID'] ?? getenv('TELEGRAM_CHAT_ID')) ?: '');
    }

    public function isConfigured(): bool
    {
        return $this->token !== '' && $this->chatId !== '';
    }

    public function sendMessage(string $text, ?array $replyMarkup = null): void
    {
        if (!$this->isConfigured() || $text === '') {
            return;
        }

        $ch = null;

        try {
            $url = 'https://api.telegram.org/bot' . rawurlencode($this->token) . '/sendMessage';
            $params = [
                'chat_id' => $this->chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ];
            if ($replyMarkup !== null) {
                $params['reply_markup'] = json_encode($replyMarkup);
            }
            $payload = http_build_query($params);

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded',
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $result = curl_exec($ch);
            if ($result === false || $result === '') {
                $curlError = curl_error($ch);
                error_log(sprintf('[%s] TelegramService cURL error: %s', date('c'), $curlError));
                throw new \RuntimeException('Telegram API: respuesta vacia o fallo de red');
            }

            $resDecoded = json_decode($result, true);
            if (isset($resDecoded['ok']) && !$resDecoded['ok']) {
                $desc = $resDecoded['description'] ?? 'sin descripcion';
                error_log(sprintf('[%s] TelegramService API error: %s', date('c'), $desc));
                $logFile = dirname(__DIR__) . '/storage/logs/telegram.log';
                @file_put_contents($logFile, sprintf("[%s] TelegramService API error: %s\n", date('c'), $desc), FILE_APPEND);
            }
        } catch (\Throwable $e) {
            $line = sprintf(
                "[%s] TelegramService error: %s\n",
                date('c'),
                $e->getMessage()
            );
            @error_log(trim($line));
            $logFile = dirname(__DIR__) . '/storage/logs/telegram.log';
            @file_put_contents($logFile, $line, FILE_APPEND);
        } finally {
            if ($ch !== null) {
                @curl_close($ch);
            }
        }
    }

    public function notifyOrderPaid(int $orderId, string $customerName, float $total): void
    {
        $name = htmlspecialchars($customerName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $totalFmt = number_format($total, 2, '.', '');

        $secret = hash_hmac('sha256', (string)$orderId, env('JWT_SECRET', 'change_this_secret'));
        $publicUrl = env('APP_PUBLIC_URL', '');
        
        if (empty($publicUrl) || (str_contains($publicUrl, 'localhost') && isset($_SERVER['HTTP_HOST']) && !str_contains($_SERVER['HTTP_HOST'], 'localhost'))) {
            $protocol = 'https';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            if (isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
                $host = $_SERVER['HTTP_X_FORWARDED_HOST'];
            }
            $publicUrl = $protocol . '://' . $host;
        } else {
            if (!str_contains($publicUrl, 'localhost') && !str_contains($publicUrl, '127.0.0.1')) {
                $publicUrl = str_replace('http://', 'https://', $publicUrl);
            }
        }
        
        if (empty($publicUrl)) {
            $publicUrl = 'http://localhost/libreria_gabi/backend/public';
        }

        $deliveryUrl = rtrim($publicUrl, '/') . "/api/orders/telegram-deliver?order_id={$orderId}&token={$secret}";

        $keyboard = [
            'inline_keyboard' => [
                [
                    [
                        'text' => '✅ Entregado',
                        'url' => $deliveryUrl
                    ]
                ]
            ]
        ];

        $this->sendMessage(
            "<b>Pedido pagado</b>\n" .
            "ID: <b>{$orderId}</b>\n" .
            "Cliente: {$name}\n" .
            "Total: <b>\${$totalFmt}</b>",
            $keyboard
        );
    }

    public function notifyLowStock(string $productTitle): void
    {
        $title = htmlspecialchars($productTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $this->sendMessage("Stock bajo: {$title}");
    }

    public function sendDocument(string $filePath, string $caption = ''): void
    {
        if (!$this->isConfigured() || !file_exists($filePath)) {
            return;
        }

        $ch = null;

        try {
            $url = 'https://api.telegram.org/bot' . rawurlencode($this->token) . '/sendDocument';
            
            $file = new \CURLFile($filePath);
            $payload = [
                'chat_id' => $this->chatId,
                'document' => $file,
                'caption' => $caption,
                'parse_mode' => 'HTML',
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 25);
            curl_setopt($ch, CURLOPT_IPRESOLVE, CURL_IPRESOLVE_V4);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $result = curl_exec($ch);
            if ($result === false || $result === '') {
                $curlError = curl_error($ch);
                error_log(sprintf('[%s] TelegramService sendDocument cURL error: %s', date('c'), $curlError));
                throw new \RuntimeException('Telegram API (sendDocument): respuesta vacia o fallo de red');
            }
        } catch (\Throwable $e) {
            $line = sprintf(
                "[%s] TelegramService sendDocument error: %s\n",
                date('c'),
                $e->getMessage()
            );
            @error_log(trim($line));
            $logFile = dirname(__DIR__) . '/storage/logs/telegram.log';
            @file_put_contents($logFile, $line, FILE_APPEND);
        } finally {
            if ($ch !== null) {
                @curl_close($ch);
            }
        }
    }
}
