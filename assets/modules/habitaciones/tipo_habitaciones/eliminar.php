<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../config/database.php';
require_role($pdo, 'Administrador');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Solicitud inválida.');
}

$roomTypeIdInput = $_POST['tipo_habitacion_id'] ?? null;
$roomTypeId = is_string($roomTypeIdInput)
    ? filter_var($roomTypeIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
if ($roomTypeId === false) {
    http_response_code(400);
    exit('ID de tipo de habitación inválido.');
}

try {
    $pdo->beginTransaction();
    $roomTypeStatement = $pdo->prepare(
        'SELECT tipo_habitacion_id
         FROM tipos_habitaciones
         WHERE tipo_habitacion_id = :tipo_habitacion_id
         FOR UPDATE'
    );
    $roomTypeStatement->execute(['tipo_habitacion_id' => $roomTypeId]);
    if (!$roomTypeStatement->fetchColumn()) {
        $pdo->rollBack();
        $_SESSION['tipos_habitaciones_error'] = 'El tipo de habitación no existe o ya fue eliminado.';
        redirect_to('/assets/modules/habitaciones/tipo_habitaciones/index.php');
    }

    $roomsStatement = $pdo->prepare(
        'SELECT COUNT(*) FROM habitaciones WHERE tipo_habitacion_id = :tipo_habitacion_id'
    );
    $roomsStatement->execute(['tipo_habitacion_id' => $roomTypeId]);
    if ((int) $roomsStatement->fetchColumn() > 0) {
        $pdo->rollBack();
        $_SESSION['tipos_habitaciones_error'] = 'No se puede eliminar este tipo porque tiene habitaciones asociadas.';
        redirect_to('/assets/modules/habitaciones/tipo_habitaciones/index.php');
    }

    $deleteStatement = $pdo->prepare(
        'DELETE FROM tipos_habitaciones WHERE tipo_habitacion_id = :tipo_habitacion_id'
    );
    $deleteStatement->execute(['tipo_habitacion_id' => $roomTypeId]);
    $pdo->commit();
    $_SESSION['tipos_habitaciones_mensaje'] = 'El tipo de habitación se eliminó correctamente.';
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Hotel Manager room type deletion failed: ' . $exception->getMessage());
    $_SESSION['tipos_habitaciones_error'] = 'No se pudo eliminar el tipo de habitación. Puede tener registros relacionados.';
}

redirect_to('/assets/modules/habitaciones/tipo_habitaciones/index.php');