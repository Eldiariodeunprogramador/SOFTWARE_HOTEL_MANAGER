<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';
require_role($pdo, 'Administrador');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Solicitud inválida.');
}

$reservationStateIdInput = $_POST['estado_reserva_id'] ?? null;
$reservationStateId = is_string($reservationStateIdInput)
    ? filter_var($reservationStateIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
if ($reservationStateId === false) {
    http_response_code(400);
    exit('ID de estado de reserva inválido.');
}

try {
    $pdo->beginTransaction();
    $activeStatesStatement = $pdo->query(
        'SELECT estado_reserva_id
         FROM estados_reservas
         WHERE activo = 1
         FOR UPDATE'
    );
    $activeStateIds = array_column($activeStatesStatement->fetchAll(), 'estado_reserva_id');

    $stateStatement = $pdo->prepare(
        'SELECT estado_reserva_id, nombre, activo
         FROM estados_reservas
         WHERE estado_reserva_id = :estado_reserva_id
         FOR UPDATE'
    );
    $stateStatement->execute(['estado_reserva_id' => $reservationStateId]);
    $reservationState = $stateStatement->fetch();
    if (!$reservationState) {
        $pdo->rollBack();
        $_SESSION['estados_reservas_error'] = 'El estado de reserva no existe o ya fue eliminado.';
        redirect_to('/assets/modules/estados_reservas/index.php');
    }
    if (!(bool) $reservationState['activo']) {
        $pdo->rollBack();
        $_SESSION['estados_reservas_error'] = 'El estado de reserva ya está inactivo.';
        redirect_to('/assets/modules/estados_reservas/index.php');
    }
    if (in_array($reservationState['nombre'], ['Pendiente', 'Confirmada', 'Cancelada'], true)) {
        $pdo->rollBack();
        $_SESSION['estados_reservas_error'] = 'Los estados Pendiente, Confirmada y Cancelada son necesarios para el flujo de reservas.';
        redirect_to('/assets/modules/estados_reservas/index.php');
    }
    if (count($activeStateIds) <= 1) {
        $pdo->rollBack();
        $_SESSION['estados_reservas_error'] = 'Debe permanecer al menos un estado de reserva activo.';
        redirect_to('/assets/modules/estados_reservas/index.php');
    }

    $deactivateStatement = $pdo->prepare(
        'UPDATE estados_reservas SET activo = 0 WHERE estado_reserva_id = :estado_reserva_id'
    );
    $deactivateStatement->execute(['estado_reserva_id' => $reservationStateId]);
    $pdo->commit();
    $_SESSION['estados_reservas_mensaje'] = 'El estado se desactivó correctamente; las reservas existentes conservaron su historial.';
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Hotel Manager reservation state deactivation failed: ' . $exception->getMessage());
    $_SESSION['estados_reservas_error'] = 'No se pudo desactivar el estado. Inténtalo de nuevo o contacta al administrador.';
}

redirect_to('/assets/modules/estados_reservas/index.php');
