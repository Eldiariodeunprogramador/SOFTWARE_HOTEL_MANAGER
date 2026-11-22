<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/helpers.php';
require_role($pdo, 'Administrador');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Solicitud inválida.');
}

$userId = filter_var(
    $_POST['usuario_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
if ($userId === false) {
    http_response_code(400);
    exit('ID de usuario inválido.');
}
if ((int) $userId === (int) $_SESSION['usuario_id']) {
    $_SESSION['usuarios_error'] = 'No puedes desactivar tu propia cuenta.';
    redirect_to('/assets/modules/usuarios/index.php');
}

try {
    $pdo->beginTransaction();
    $adminLockStatement = $pdo->prepare(
        'SELECT u.usuario_id
         FROM usuarios u
         INNER JOIN usuario_rol ur ON ur.usuario_id = u.usuario_id
         INNER JOIN roles r ON r.rol_id = ur.rol_id
         WHERE u.activo = 1 AND r.activo = 1 AND r.nombre = :nombre
         FOR UPDATE'
    );
    $adminLockStatement->execute(['nombre' => 'Administrador']);
    $activeAdminIds = array_column($adminLockStatement->fetchAll(), 'usuario_id');

    $userStatement = $pdo->prepare(
        'SELECT u.usuario_id,
                EXISTS (
                    SELECT 1
                    FROM usuario_rol ur
                    INNER JOIN roles r ON r.rol_id = ur.rol_id
                    WHERE ur.usuario_id = u.usuario_id
                        AND r.activo = 1 AND r.nombre = :nombre
                ) AS es_administrador
         FROM usuarios u
         WHERE u.usuario_id = :usuario_id AND u.activo = 1
         LIMIT 1'
    );
    $userStatement->execute([
        'nombre' => 'Administrador',
        'usuario_id' => $userId,
    ]);
    $user = $userStatement->fetch();
    if (!$user) {
        $pdo->rollBack();
        $_SESSION['usuarios_error'] = 'El usuario no existe o ya está inactivo.';
        redirect_to('/assets/modules/usuarios/index.php');
    }

    if ((bool) $user['es_administrador'] && count($activeAdminIds) <= 1) {
        $pdo->rollBack();
        $_SESSION['usuarios_error'] = 'No puedes desactivar al último administrador activo.';
        redirect_to('/assets/modules/usuarios/index.php');
    }

    $updateStatement = $pdo->prepare(
        'UPDATE usuarios SET activo = 0 WHERE usuario_id = :usuario_id'
    );
    $updateStatement->execute(['usuario_id' => $userId]);
    $pdo->commit();
    $_SESSION['usuarios_mensaje'] = 'El usuario se desactivó correctamente.';
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Hotel Manager user deactivation failed: ' . $exception->getMessage());
    $_SESSION['usuarios_error'] = 'No se pudo desactivar el usuario. Inténtalo de nuevo o contacta al administrador del sistema.';
}

redirect_to('/assets/modules/usuarios/index.php');