<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';
require_role($pdo, 'Administrador');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Solicitud inválida.');
}

$roomIdInput = $_POST['habitacion_id'] ?? null;
$roomId = is_string($roomIdInput)
    ? filter_var($roomIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
if ($roomId === false) {
    http_response_code(400);
    exit('ID de habitación inválido.');
}

try {
    $pdo->beginTransaction();
    $roomStatement = $pdo->prepare(
        'SELECT habitacion_id FROM habitaciones WHERE habitacion_id = :habitacion_id FOR UPDATE'
    );
    $roomStatement->execute(['habitacion_id' => $roomId]);
    if (!$roomStatement->fetchColumn()) {
        $pdo->rollBack();
        $_SESSION['habitaciones_error'] = 'La habitación no existe o ya fue eliminada.';
        redirect_to('/assets/modules/habitaciones/index.php');
    }

    $reservationsStatement = $pdo->prepare(
        'SELECT COUNT(*) FROM reservas WHERE habitacion_id = :habitacion_id'
    );
    $reservationsStatement->execute(['habitacion_id' => $roomId]);
    if ((int) $reservationsStatement->fetchColumn() > 0) {
        $pdo->rollBack();
        $_SESSION['habitaciones_error'] = 'No se puede eliminar esta habitación porque tiene reservas registradas.';
        redirect_to('/assets/modules/habitaciones/index.php');
    }

    $deleteStatement = $pdo->prepare(
        'DELETE FROM habitaciones WHERE habitacion_id = :habitacion_id'
    );
    $deleteStatement->execute(['habitacion_id' => $roomId]);
    $pdo->commit();
    $_SESSION['habitaciones_mensaje'] = 'La habitación se eliminó correctamente.';
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Hotel Manager room deletion failed: ' . $exception->getMessage());
    $_SESSION['habitaciones_error'] = 'No se pudo eliminar la habitación. Puede tener registros relacionados.';
}

redirect_to('/assets/modules/habitaciones/index.php');