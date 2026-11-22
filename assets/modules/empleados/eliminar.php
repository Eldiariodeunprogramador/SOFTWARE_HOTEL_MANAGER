<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';
require_role($pdo, 'Administrador');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Solicitud inválida.');
}

$employeeIdInput = $_POST['empleado_id'] ?? null;
$employeeId = is_string($employeeIdInput)
    ? filter_var($employeeIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
if ($employeeId === false) {
    http_response_code(400);
    exit('ID de empleado inválido.');
}

try {
    $pdo->beginTransaction();
    $employeeStatement = $pdo->prepare(
        'SELECT empleado_id, activo
         FROM empleados
         WHERE empleado_id = :empleado_id
         FOR UPDATE'
    );
    $employeeStatement->execute(['empleado_id' => $employeeId]);
    $employee = $employeeStatement->fetch();
    if (!$employee || !(bool) $employee['activo']) {
        $pdo->rollBack();
        $_SESSION['empleados_error'] = 'El empleado no existe o ya está inactivo.';
        redirect_to('/assets/modules/empleados/index.php');
    }

    $deactivateStatement = $pdo->prepare(
        'UPDATE empleados SET activo = 0 WHERE empleado_id = :empleado_id'
    );
    $deactivateStatement->execute(['empleado_id' => $employeeId]);
    $pdo->commit();
    $_SESSION['empleados_mensaje'] = 'El empleado se desactivó correctamente.';
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Hotel Manager employee deactivation failed: ' . $exception->getMessage());
    $_SESSION['empleados_error'] = 'No se pudo desactivar el empleado.';
}

redirect_to('/assets/modules/empleados/index.php');