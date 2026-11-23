<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('pagos.ver');
require_once __DIR__ . '/../../config/database.php';

$paymentIdInput = $_GET['id'] ?? '';
$paymentId = is_string($paymentIdInput)
    ? filter_var($paymentIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
if ($paymentId === false) {
    http_response_code(400);
    exit('ID de pago inválido.');
}

$canSeeAllPayments = user_has_role($pdo, 'Administrador') || user_has_role($pdo, 'Empleado');
$paymentSql =
    'SELECT p.pago_id, p.monto, p.fecha_pago, p.referencia, p.observaciones,
            r.reserva_id,             r.fecha_entrada, r.fecha_salida, r.numero_huespedes,
            DATEDIFF(r.fecha_salida, r.fecha_entrada) AS noches,
            r.precio_noche,
            ROUND(r.precio_noche * DATEDIFF(r.fecha_salida, r.fecha_entrada), 2) AS total_reserva,
            CONCAT(persona.nombres, \' \', persona.apellidos) AS cliente,
            td.abreviatura AS tipo_documento, persona.numero_documento,
            h.numero AS habitacion, th.nombre AS tipo_habitacion,
            mp.nombre AS metodo_pago, ep.nombre AS estado_pago
     FROM pagos p
     INNER JOIN reservas r ON r.reserva_id = p.reserva_id
     INNER JOIN clientes c ON c.cliente_id = r.cliente_id
     INNER JOIN personas persona ON persona.persona_id = c.persona_id
     INNER JOIN tipos_documentos td ON td.tipo_documento_id = persona.tipo_documento_id
     INNER JOIN habitaciones h ON h.habitacion_id = r.habitacion_id
     INNER JOIN tipos_habitaciones th ON th.tipo_habitacion_id = h.tipo_habitacion_id
     INNER JOIN metodos_pagos mp ON mp.metodo_pago_id = p.metodo_pago_id
     INNER JOIN estados_pagos ep ON ep.estado_pago_id = p.estado_pago_id
     WHERE p.pago_id = :pago_id';
if (!$canSeeAllPayments) {
    $paymentSql .=
        ' AND c.persona_id = (
            SELECT persona_id FROM usuarios WHERE usuario_id = :usuario_id
        )';
}
$paymentStatement = $pdo->prepare($paymentSql);
$paymentParameters = ['pago_id' => $paymentId];
if (!$canSeeAllPayments) {
    $paymentParameters['usuario_id'] = $_SESSION['usuario_id'];
}
$paymentStatement->execute($paymentParameters);
$payment = $paymentStatement->fetch();
if (!$payment) {
    http_response_code(404);
    exit('No se encontró el pago solicitado.');
}
if ($payment['estado_pago'] !== 'Pagado') {
    http_response_code(409);
    exit('Solo se pueden descargar facturas de pagos confirmados.');
}

function payment_invoice_pdf(array $lines): string
{
    $content = '';
    $y = 790;
    foreach ($lines as $line) {
        if (!empty($line['rule'])) {
            $content .= "0.6 w 50 {$y} m 545 {$y} l S\n";
            $y -= 14;
            continue;
        }

        $font = $line['font'] ?? 'F1';
        $fontSize = $line['size'] ?? 10;
        $spacing = $line['spacing'] ?? 17;
        $encodedText = iconv('UTF-8', 'Windows-1252//TRANSLIT', (string) $line['text']);
        if ($encodedText === false) {
            throw new RuntimeException('Invoice text encoding failed.');
        }

        foreach (explode("\n", wordwrap($encodedText, 85, "\n", true)) as $wrappedLine) {
            $content .= 'BT /' . $font . ' ' . $fontSize . ' Tf 1 0 0 1 50 ' . $y . ' Tm '
                . '<' . strtoupper(bin2hex($wrappedLine)) . "> Tj ET\n";
            $y -= $spacing;
        }
    }

    $objects = [
        1 => '<< /Type /Catalog /Pages 2 0 R >>',
        2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] '
            . '/Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >>',
        4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
        5 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>',
        6 => '<< /Length ' . strlen($content) . " >>\nstream\n" . $content . 'endstream',
    ];
    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [0];
    foreach ($objects as $objectId => $objectBody) {
        $offsets[$objectId] = strlen($pdf);
        $pdf .= $objectId . " 0 obj\n" . $objectBody . "\nendobj\n";
    }

    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    for ($objectId = 1; $objectId <= count($objects); $objectId++) {
        $pdf .= sprintf("%010d 00000 n \n", $offsets[$objectId]);
    }
    $pdf .= "trailer\n<< /Size " . (count($objects) + 1)
        . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF\n";

    return $pdf;
}

$documentNumber = trim($payment['tipo_documento'] . ' ' . $payment['numero_documento']);
$invoiceLines = [
    ['text' => 'HOTEL PREMIUM', 'font' => 'F2', 'size' => 20, 'spacing' => 28],
    ['text' => 'FACTURA / COMPROBANTE DE PAGO', 'font' => 'F2', 'size' => 13, 'spacing' => 24],
    ['text' => 'Comprobante No.: FAC-' . str_pad((string) $payment['pago_id'], 8, '0', STR_PAD_LEFT)],
    ['text' => 'Fecha de emisión: ' . ($payment['fecha_pago'] ?? 'No indicada')],
    ['rule' => true],
    ['text' => 'DATOS DEL CLIENTE', 'font' => 'F2', 'size' => 11],
    ['text' => 'Nombre: ' . $payment['cliente']],
    ['text' => 'Documento: ' . ($documentNumber !== '' ? $documentNumber : 'No registrado')],
    ['rule' => true],
    ['text' => 'DETALLE DE LA RESERVA', 'font' => 'F2', 'size' => 11],
    ['text' => 'Reserva: #' . $payment['reserva_id']],
    ['text' => 'Habitación: ' . $payment['habitacion'] . ' — ' . $payment['tipo_habitacion']],
    ['text' => 'Entrada: ' . $payment['fecha_entrada'] . '    Salida: ' . $payment['fecha_salida']],
    ['text' => 'Huéspedes: ' . $payment['numero_huespedes']],
    ['text' => 'Noches: ' . (int) $payment['noches']],
    ['text' => 'Tarifa por noche: ' . number_format((float) $payment['precio_noche'], 2, ',', '.')],
    ['text' => 'Total de la reserva: ' . number_format((float) $payment['total_reserva'], 2, ',', '.')],
    ['rule' => true],
    ['text' => 'DETALLE DEL PAGO', 'font' => 'F2', 'size' => 11],
    ['text' => 'Monto pagado: ' . number_format((float) $payment['monto'], 2, ',', '.'), 'font' => 'F2', 'size' => 12],
    ['text' => 'Método: ' . $payment['metodo_pago']],
    ['text' => 'Estado: ' . $payment['estado_pago']],
    ['text' => 'Referencia: ' . ($payment['referencia'] ?? 'No indicada')],
];
if (trim((string) ($payment['observaciones'] ?? '')) !== '') {
    $invoiceLines[] = ['text' => 'Observaciones: ' . $payment['observaciones']];
}
$invoiceLines[] = ['rule' => true];
$invoiceLines[] = ['text' => 'Comprobante generado con los datos registrados en Hotel Premium.', 'size' => 8];
$invoiceLines[] = ['text' => 'No incluye datos fiscales o impuestos que no estén registrados en el sistema.', 'size' => 8];

try {
    $pdf = payment_invoice_pdf($invoiceLines);
} catch (RuntimeException $exception) {
    error_log('Hotel Manager invoice PDF generation failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('No se pudo generar la factura.');
}

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="factura-' . (int) $payment['pago_id'] . '.pdf"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, no-store');
echo $pdf;
exit;
