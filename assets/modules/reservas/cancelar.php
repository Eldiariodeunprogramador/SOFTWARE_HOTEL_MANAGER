<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('reservas.cancelar');
require_once __DIR__ . '/../../config/database.php';

$canManageAll = user_has_role($pdo, 'Administrador') || user_has_role($pdo, 'Empleado');
$isCustomer = user_has_role($pdo, 'Cliente') && !$canManageAll;
$returnPath = $isCustomer
    ? '/assets/modules/reservas/mis_reservas.php'
    : '/assets/modules/reservas/index.php';

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
    $cancelledStateStatement = $pdo->prepare(
        'SELECT estado_reserva_id
         FROM estados_reservas
         WHERE nombre = :nombre
         LIMIT 1
         FOR UPDATE'
    );
    $cancelledStateStatement->execute(['nombre' => 'Cancelada']);
    $cancelledStateId = $cancelledStateStatement->fetchColumn();
    if (!$cancelledStateId) {
        $pdo->rollBack();
        $_SESSION['reservas_error'] = 'No existe el estado Cancelada. Verifica los estados de reserva configurados.';
        redirect_to($returnPath);

    }

    $reservationSql =
        'SELECT r.reserva_id, er.nombre AS estado
         FROM reservas r
         INNER JOIN clientes c ON c.cliente_id = r.cliente_id
         INNER JOIN estados_reservas er ON er.estado_reserva_id = r.estado_reserva_id
         WHERE r.reserva_id = :reserva_id';
    if (!$canManageAll) {
        $reservationSql .=
            ' AND c.persona_id = (
                SELECT persona_id FROM usuarios WHERE usuario_id = :usuario_id
            )';
    }
    $reservationSql .= ' FOR UPDATE';
    $reservationStatement = $pdo->prepare($reservationSql);
    $reservationParameters = ['reserva_id' => $reservationId];
    if (!$canManageAll) {
        $reservationParameters['usuario_id'] = $_SESSION['usuario_id'];
    }
    $reservationStatement->execute($reservationParameters);
    $reservation = $reservationStatement->fetch();
    if (!$reservation) {
        $pdo->rollBack();
        $_SESSION['reservas_error'] = 'La reserva no existe o ya fue eliminada.';
        redirect_to($returnPath);
    }
    if ($reservation['estado'] === 'Cancelada') {
        $pdo->rollBack();
        $_SESSION['reservas_error'] = 'La reserva ya está cancelada.';
        redirect_to($returnPath);
    }
    if ($reservation['estado'] === 'Finalizada') {
        $pdo->rollBack();
        $_SESSION['reservas_error'] = 'No se puede cancelar una reserva finalizada.';
        redirect_to($returnPath);
    }
    if ($isCustomer && $reservation['estado'] !== 'Pendiente') {
        $pdo->rollBack();
        $_SESSION['reservas_error'] = 'Solo puedes cancelar solicitudes de reserva pendientes.';
        redirect_to($returnPath);
    }

    $updateStatement = $pdo->prepare(
        'UPDATE reservas
         SET estado_reserva_id = :estado_reserva_id
         WHERE reserva_id = :reserva_id'
    );
    $updateStatement->execute([
        'estado_reserva_id' => $cancelledStateId,
        'reserva_id' => $reservationId,
    ]);
    $pdo->commit();
    $_SESSION['reservas_mensaje'] = 'La reserva se canceló correctamente.';
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Hotel Manager reservation cancellation failed: ' . $exception->getMessage());
    $_SESSION['reservas_error'] = 'No se pudo cancelar la reserva. Inténtalo de nuevo o contacta al administrador.';
}

redirect_to($returnPath);