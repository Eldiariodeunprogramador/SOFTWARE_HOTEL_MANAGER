<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../config/database.php';
require_role($pdo, 'Administrador');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Solicitud inválida.');
}

$roomStateIdInput = $_POST['estado_habitacion_id'] ?? null;
$roomStateId = is_string($roomStateIdInput)
    ? filter_var($roomStateIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
if ($roomStateId === false) {
    http_response_code(400);
    exit('ID de estado de habitación inválido.');
}

try {
    $pdo->beginTransaction();
    $roomStateStatement = $pdo->prepare(
        'SELECT estado_habitacion_id
         FROM estados_habitaciones
         WHERE estado_habitacion_id = :estado_habitacion_id
         FOR UPDATE'
    );
    $roomStateStatement->execute(['estado_habitacion_id' => $roomStateId]);
    if (!$roomStateStatement->fetchColumn()) {
        $pdo->rollBack();
        $_SESSION['estados_habitaciones_error'] = 'El estado de habitación no existe o ya fue eliminado.';
        redirect_to('/assets/modules/habitaciones/estados_habitaciones/index.php');
    }

    $roomsStatement = $pdo->prepare(
        'SELECT COUNT(*) FROM habitaciones WHERE estado_habitacion_id = :estado_habitacion_id'
    );
    $roomsStatement->execute(['estado_habitacion_id' => $roomStateId]);
    if ((int) $roomsStatement->fetchColumn() > 0) {
        $pdo->rollBack();
        $_SESSION['estados_habitaciones_error'] = 'No se puede eliminar este estado porque tiene habitaciones asociadas.';
        redirect_to('/assets/modules/habitaciones/estados_habitaciones/index.php');
    }

    $deleteStatement = $pdo->prepare(
        'DELETE FROM estados_habitaciones WHERE estado_habitacion_id = :estado_habitacion_id'
    );
    $deleteStatement->execute(['estado_habitacion_id' => $roomStateId]);
    $pdo->commit();
    $_SESSION['estados_habitaciones_mensaje'] = 'El estado de habitación se eliminó correctamente.';
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Hotel Manager room state deletion failed: ' . $exception->getMessage());
    $_SESSION['estados_habitaciones_error'] = 'No se pudo eliminar el estado de habitación. Puede tener registros relacionados.';
}

redirect_to('/assets/modules/habitaciones/estados_habitaciones/index.php');