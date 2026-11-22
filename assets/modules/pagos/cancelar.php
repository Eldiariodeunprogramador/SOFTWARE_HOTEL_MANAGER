<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';
require_role($pdo, 'Administrador');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Solicitud inválida.');
}

$paymentIdInput = $_POST['pago_id'] ?? null;
$paymentId = is_string($paymentIdInput)
    ? filter_var($paymentIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
if ($paymentId === false) {
    http_response_code(400);
    exit('ID de pago inválido.');
}

try {
    $pdo->beginTransaction();
    $cancelledStateStatement = $pdo->prepare(
        'SELECT estado_pago_id
         FROM estados_pagos
         WHERE nombre = :nombre
         LIMIT 1
         FOR UPDATE'
    );
    $cancelledStateStatement->execute(['nombre' => 'Cancelado']);
    $cancelledStateId = $cancelledStateStatement->fetchColumn();
    if (!$cancelledStateId) {
        $pdo->rollBack();
        $_SESSION['pagos_error'] = 'No existe el estado Cancelado. Ejecuta la migración de estados de pago.';
        redirect_to('/assets/modules/pagos/index.php');
    }

    $paymentStatement = $pdo->prepare(
        'SELECT p.pago_id, ep.nombre AS estado_pago
         FROM pagos p
         INNER JOIN estados_pagos ep ON ep.estado_pago_id = p.estado_pago_id
         WHERE p.pago_id = :pago_id
         FOR UPDATE'
    );
    $paymentStatement->execute(['pago_id' => $paymentId]);
    $payment = $paymentStatement->fetch();
    if (!$payment) {
        $pdo->rollBack();
        $_SESSION['pagos_error'] = 'El pago no existe o ya fue eliminado.';
        redirect_to('/assets/modules/pagos/index.php');
    }
    if ($payment['estado_pago'] === 'Cancelado') {
        $pdo->rollBack();
        $_SESSION['pagos_error'] = 'El pago ya está cancelado.';
        redirect_to('/assets/modules/pagos/index.php');
    }
    if ($payment['estado_pago'] !== 'Pendiente') {
        $pdo->rollBack();
        $_SESSION['pagos_error'] = 'Solo se pueden cancelar pagos pendientes. Los pagos recibidos deben seguir el proceso de reembolso.';
        redirect_to('/assets/modules/pagos/index.php');
    }

    $updateStatement = $pdo->prepare(
        'UPDATE pagos
         SET estado_pago_id = :estado_pago_id
         WHERE pago_id = :pago_id'
    );
    $updateStatement->execute([
        'estado_pago_id' => $cancelledStateId,
        'pago_id' => $paymentId,
    ]);
    $pdo->commit();
    $_SESSION['pagos_mensaje'] = 'El pago pendiente se canceló correctamente.';
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Hotel Manager payment cancellation failed: ' . $exception->getMessage());
    $_SESSION['pagos_error'] = 'No se pudo cancelar el pago. Inténtalo de nuevo o contacta al administrador.';
}

redirect_to('/assets/modules/pagos/index.php');