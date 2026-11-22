<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';
require_permission('roles.eliminar');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Solicitud inválida.');
}

$roleId = filter_var(
    $_POST['rol_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
if ($roleId === false) {
    http_response_code(400);
    exit('ID de rol inválido.');
}

try {
    $pdo->beginTransaction();
    $roleStatement = $pdo->prepare(
        'SELECT nombre, activo FROM roles WHERE rol_id = :rol_id FOR UPDATE'
    );
    $roleStatement->execute(['rol_id' => $roleId]);
    $role = $roleStatement->fetch();
    if (!$role) {
        $pdo->rollBack();
        $_SESSION['roles_error'] = 'El rol no existe o ya fue eliminado.';
        redirect_to('/assets/modules/roles/index.php');
    }

    if ($role['nombre'] === 'Administrador') {
        $pdo->rollBack();
        $_SESSION['roles_error'] = 'El rol Administrador está protegido y no se puede desactivar.';
        redirect_to('/assets/modules/roles/index.php');
    }

    if (!(bool) $role['activo']) {
        $pdo->rollBack();
        $_SESSION['roles_error'] = 'El rol ya está inactivo.';
        redirect_to('/assets/modules/roles/index.php');
    }

    $assignedUsersStatement = $pdo->prepare(
        'SELECT u.usuario_id
         FROM usuario_rol ur
         INNER JOIN usuarios u ON u.usuario_id = ur.usuario_id
         WHERE ur.rol_id = :rol_id AND u.activo = 1
         FOR UPDATE'
    );
    $assignedUsersStatement->execute(['rol_id' => $roleId]);
    if ($assignedUsersStatement->fetch()) {
        $pdo->rollBack();
        $_SESSION['roles_error'] = 'No se puede desactivar un rol asignado a usuarios activos. Reasigna esos usuarios primero.';
        redirect_to('/assets/modules/roles/index.php');
    }

    $deactivateStatement = $pdo->prepare(
        'UPDATE roles SET activo = 0 WHERE rol_id = :rol_id'
    );
    $deactivateStatement->execute(['rol_id' => $roleId]);
    $pdo->commit();
    $_SESSION['roles_mensaje'] = 'El rol se desactivó correctamente.';
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Hotel Manager role deactivation failed: ' . $exception->getMessage());
    $_SESSION['roles_error'] = 'No se pudo desactivar el rol. Inténtalo de nuevo o contacta al administrador del sistema.';
}

redirect_to('/assets/modules/roles/index.php');