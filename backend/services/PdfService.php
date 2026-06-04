<?php

require_once __DIR__ . '/../public/vendor/autoload.php';

class PdfService
{
    /**
     * Genera un PDF estructurado para impresión.
     * Retorna la ruta del archivo PDF generado en disco.
     */
    public function generateOrderPdf(array $order, array $user, array $address, array $items): string
    {
        $pdf = new FPDF('P', 'mm', 'A4');
        $pdf->AddPage();
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(true, 15);

        $logoPath = dirname(__DIR__, 2) . '/frontend/src/assets/logo.png';
        $logoExists = file_exists($logoPath);

        // ==========================================
        // 1. SECCIÓN SUPERIOR (ETIQUETA DE ENVÍO)
        // ==========================================
        
        // Cabecera / Logo
        if ($logoExists) {
            try {
                // Intentamos meter el logo. Si da error de canal alfa, se captura
                $pdf->Image($logoPath, 15, 15, 30);
                $pdf->SetY(15);
            } catch (\Throwable $e) {
                // Fallback de texto si falla la imagen por canal alfa
                $pdf->SetFont('Arial', 'B', 16);
                $pdf->SetTextColor(26, 35, 61);
                $pdf->Cell(0, 10, utf8_decode("LIBRERÍA GABI"), 0, 1, 'L');
            }
        } else {
            $pdf->SetFont('Arial', 'B', 16);
            $pdf->SetTextColor(26, 35, 61);
            $pdf->Cell(0, 10, utf8_decode("LIBRERÍA GABI"), 0, 1, 'L');
        }

        // Título de la etiqueta y Fecha
        $pdf->SetXY(120, 15);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetTextColor(255, 159, 67); // Naranja corporativo
        $pdf->Cell(75, 6, utf8_decode("ETIQUETA DE ENVÍO"), 0, 1, 'R');
        $pdf->SetX(120);
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(100, 116, 139);
        $pdf->Cell(75, 6, "Fecha: " . date('d/m/Y H:i', strtotime($order['created_at'])), 0, 1, 'R');

        $pdf->SetY(40);

        // Recuadro del destinatario
        $pdf->SetDrawColor(226, 232, 240);
        $pdf->SetFillColor(248, 250, 252);
        $pdf->Rect(15, 40, 180, 55, 'DF');

        // Datos del Destinatario
        $pdf->SetXY(20, 45);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetTextColor(26, 35, 61);
        $pdf->Cell(0, 6, utf8_decode("DESTINATARIO:"), 0, 1);
        
        $pdf->SetX(20);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->Cell(0, 6, utf8_decode($user['name']), 0, 1);

        $pdf->SetX(20);
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(74, 85, 104);
        $pdf->Cell(0, 6, utf8_decode("Teléfono: " . ($user['phone'] ?? 'No provisto')), 0, 1);

        $pdf->Ln(2);
        $pdf->SetX(20);
        $pdf->SetFont('Arial', 'B', 11);
        $pdf->SetTextColor(26, 35, 61);
        $pdf->Cell(0, 6, utf8_decode("DIRECCIÓN DE ENVÍO:"), 0, 1);

        $pdf->SetX(20);
        $pdf->SetFont('Arial', '', 11);
        $pdf->SetTextColor(26, 35, 61);
        $pdf->MultiCell(170, 5, utf8_decode($address['address_line'] . "\n" . $address['postal_code'] . " - " . $address['city'] . " (" . $address['country'] . ")"), 0, 'L');

        // ==========================================
        // 2. LÍNEA DE CORTE
        // ==========================================
        $pdf->SetY(105);
        $pdf->SetFont('Arial', 'I', 9);
        $pdf->SetTextColor(148, 163, 184);
        $pdf->Cell(0, 10, "------------------------- LINEAS PARA RECORTAR -------------------------", 0, 1, 'C');

        // ==========================================
        // 3. SECCIÓN INFERIOR (TICKET DE COMPRA)
        // ==========================================
        $pdf->SetY(120);
        
        // Encabezado del ticket
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->SetTextColor(26, 35, 61);
        $pdf->Cell(100, 8, utf8_decode("TICKET DE COMPRA"), 0, 0);
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetTextColor(255, 159, 67);
        $pdf->Cell(80, 8, utf8_decode("Pedido N°: #" . $order['id']), 0, 1, 'R');

        $pdf->SetTextColor(100, 116, 139);
        $pdf->SetFont('Arial', '', 9);
        $pdf->Cell(0, 4, "Fecha pedido: " . date('d/m/Y H:i', strtotime($order['created_at'])), 0, 1, 'R');

        $pdf->Ln(4);

        // Datos del cliente en el ticket
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->SetTextColor(26, 35, 61);
        $pdf->Cell(0, 5, utf8_decode("DATOS DE FACTURACIÓN Y CONTACTO:"), 0, 1);
        
        $pdf->SetFont('Arial', '', 10);
        $pdf->SetTextColor(74, 85, 104);
        $pdf->Cell(90, 5, utf8_decode("Nombre: " . $user['name']), 0, 0);
        $pdf->Cell(90, 5, utf8_decode("Email: " . $user['email']), 0, 1);
        $pdf->Cell(0, 5, utf8_decode("Teléfono: " . ($user['phone'] ?? 'No provisto')), 0, 1);

        $pdf->Ln(5);

        // Tabla de Artículos
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFillColor(26, 35, 61);
        
        // Cabecera de tabla
        $pdf->Cell(95, 7, utf8_decode(" Artículo"), 0, 0, 'L', true);
        $pdf->Cell(25, 7, utf8_decode("Cant."), 0, 0, 'C', true);
        $pdf->Cell(30, 7, utf8_decode("P. Unitario"), 0, 0, 'R', true);
        $pdf->Cell(30, 7, utf8_decode("Subtotal"), 0, 1, 'R', true);

        $pdf->SetFont('Arial', '', 9);
        $pdf->SetTextColor(26, 35, 61);
        $pdf->SetDrawColor(241, 245, 249);
        
        $fill = false;
        foreach ($items as $item) {
            $pdf->SetFillColor($fill ? 248 : 255);
            $subtotal = (float)$item['price'] * (int)$item['quantity'];

            // Celda de artículo (con MultiCell o ajuste para evitar que se desborde el nombre)
            $x = $pdf->GetX();
            $y = $pdf->GetY();
            $pdf->Cell(95, 7, utf8_decode(" " . $item['name']), 'B', 0, 'L', $fill);
            $pdf->Cell(25, 7, $item['quantity'], 'B', 0, 'C', $fill);
            $pdf->Cell(30, 7, "$" . number_format((float)$item['price'], 2), 'B', 0, 'R', $fill);
            $pdf->Cell(30, 7, "$" . number_format($subtotal, 2), 'B', 1, 'R', $fill);
            
            $fill = !$fill;
        }

        $pdf->Ln(4);

        // Totales y Método de pago
        $pdf->SetFont('Arial', 'B', 10);
        $pdf->Cell(100, 6, utf8_decode("Método de Pago: ") . ($order['payment_method'] === 'card_online' ? 'Tarjeta (online)' : 'Pago al recibir (contra reembolso)'), 0, 0);
        
        $pdf->SetFont('Arial', 'B', 12);
        $pdf->SetTextColor(26, 35, 61);
        $pdf->Cell(45, 8, "TOTAL:", 0, 0, 'R');
        $pdf->SetTextColor(255, 159, 67);
        $pdf->Cell(35, 8, "$" . number_format((float)$order['total_price'], 2), 0, 1, 'R');

        // Carpeta temporal
        $dir = dirname(__DIR__) . '/storage/uploads/pdf';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $filename = 'pedido_' . $order['id'] . '_' . time() . '.pdf';
        $filePath = $dir . '/' . $filename;
        
        $pdf->Output('F', $filePath);

        return $filePath;
    }
}
