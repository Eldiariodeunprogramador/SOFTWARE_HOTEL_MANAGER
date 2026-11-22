<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('reservas.editar');
require_once __DIR__ . '/../../config/database.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Solicitud inválida.');
}

$reservationIdInput = $_POST['reserva_id'] ?? null;
$reservationId = is_string($reservationIdInput)
    ? filter_var($reservationIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
if ($reservationId === false) {
    http_response_code(400);
    exit('ID de reserva inválido.');
}

try {
    $pdo->beginTransaction();
    $confirmedStateStatement = $pdo->prepare(
        'SELECT estado_reserva_id
         FROM estados_reservas
         WHERE nombre = :nombre AND activo = 1
         LIMIT 1
         FOR UPDATE'
    );
    $confirmedStateStatement->execute(['nombre' => 'Confirmada']);
    $confirmedStateId = $confirmedStateStatement->fetchColumn();
    if (!$confirmedStateId) {
        $pdo->rollBack();
        $_SESSION['reservas_error'] = 'No existe un estado Confirmada activo. Verifica los estados de reserva configurados.';
        redirect_to('/assets/modules/reservas/index.php');
    }

    $reservationStatement = $pdo->prepare(
        'SELECT r.reserva_id, er.nombre AS estado
         FROM reservas r
         INNER JOIN estados_reservas er ON er.estado_reserva_id = r.estado_reserva_id
         WHERE r.reserva_id = :reserva_id
         FOR UPDATE'
    );
    $reservationStatement->execute(['reserva_id' => $reservationId]);
    $reservation = $reservationStatement->fetch();
    if (!$reservation) {
        $pdo->rollBack();
        $_SESSION['reservas_error'] = 'La reserva no existe o ya fue eliminada.';
        redirect_to('/assets/modules/reservas/index.php');
    }
    if ($reservation['estado'] === 'Confirmada') {
        $pdo->rollBack();
        $_SESSION['reservas_error'] = 'La reserva ya está confirmada.';
        redirect_to('/assets/modules/reservas/index.php');
    }
    if ($reservation['estado'] !== 'Pendiente') {
        $pdo->rollBack();
        $_SESSION['reservas_error'] = 'Solo se pueden confirmar reservas pendientes.';
        redirect_to('/assets/modules/reservas/index.php');
    }

    $updateStatement = $pdo->prepare(
        'UPDATE reservas
         SET estado_reserva_id = :estado_reserva_id
         WHERE reserva_id = :reserva_id'
    );
    $updateStatement->execute([
        'estado_reserva_id' => $confirmedStateId,
        'reserva_id' => $reservationId,
    ]);
    $pdo->commit();
    $_SESSION['reservas_mensaje'] = 'La reserva se confirmó correctamente.';
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Hotel Manager reservation confirmation failed: ' . $exception->getMessage());
    $_SESSION['reservas_error'] = 'No se pudo confirmar la reserva. Inténtalo de nuevo o contacta al administrador.';
}

redirect_to('/assets/modules/reservas/index.php');
