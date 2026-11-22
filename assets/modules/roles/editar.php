<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/helpers.php';
require_permission('roles.editar');

$roleIdInput = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['rol_id'] ?? '')
    : ($_GET['id'] ?? '');
$roleId = filter_var($roleIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($roleId === false) {
    http_response_code(400);
    exit('ID de rol inválido.');
}

$roleStatement = $pdo->prepare(
    'SELECT rol_id, nombre, descripcion, activo
     FROM roles
     WHERE rol_id = :rol_id
     LIMIT 1'
);
$roleStatement->execute(['rol_id' => $roleId]);
$role = $roleStatement->fetch();
if (!$role) {
    http_response_code(404);
    exit('No se encontró el rol solicitado.');
}

$permissionStatement = $pdo->prepare(
    'SELECT p.permiso_id
     FROM rol_permiso rp
     INNER JOIN permisos p ON p.permiso_id = rp.permiso_id
     WHERE rp.rol_id = :rol_id'
);
$permissionStatement->execute(['rol_id' => $roleId]);
$currentPermissionIds = array_map('intval', array_column($permissionStatement->fetchAll(), 'permiso_id'));
$permissions = roles_permisos_activos($pdo);
$protectedRole = $role['nombre'] === 'Administrador';
$roleName = $role['nombre'];
$description = $role['descripcion'] ?? '';
$active = (bool) $role['activo'];
$selectedPermissionIds = $currentPermissionIds;
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Solicitud inválida. Vuelve a cargar el formulario e inténtalo de nuevo.');
    }

    $descriptionInput = $_POST['descripcion'] ?? '';
    if (!is_string($descriptionInput)) {
        $description = '';
        $errorMessage = 'La descripción enviada no es válida.';
    } else {
        $description = trim($descriptionInput);
    }

    if ($protectedRole) {
        $roleName = $role['nombre'];
        $active = (bool) $role['activo'];
        $selectedPermissionIds = $currentPermissionIds;
    } else {
        $roleNameInput = $_POST['nombre'] ?? '';
        $activeInput = $_POST['activo'] ?? '0';
        $selectedPermissionIds = roles_parsear_permisos($_POST['permission_ids'] ?? []);

        if (!is_string($roleNameInput) || !is_string($activeInput)) {
            $errorMessage = 'Los datos del rol no son válidos.';
        } else {
            $roleName = trim($roleNameInput);
            $active = $activeInput === '1';
        }

        if ($errorMessage === ''
            && ($roleName === '' || strlen($roleName) > 50 || strlen($description) > 150
                || !in_array($activeInput, ['0', '1'], true)
                || $selectedPermissionIds === null
                || !roles_permisos_validos($pdo, $selectedPermissionIds))) {
            $errorMessage = 'Verifica el nombre, la descripción (máximo 150 caracteres), el estado y los permisos seleccionados.';
        }

        if ($errorMessage === '') {
            $duplicateStatement = $pdo->prepare(
                'SELECT COUNT(*) FROM roles WHERE nombre = :nombre AND rol_id <> :rol_id'
            );
            $duplicateStatement->execute(['nombre' => $roleName, 'rol_id' => $roleId]);
            if ((int) $duplicateStatement->fetchColumn() > 0) {
                $errorMessage = 'Ya existe un rol con ese nombre.';
            }
        }
    }

    if ($errorMessage === '' && strlen($description) > 150) {
        $errorMessage = 'La descripción no puede superar los 150 caracteres.';
    }

    if ($errorMessage === '') {
        try {
            $pdo->beginTransaction();
            if (!$protectedRole && $activeInput === '0' && (bool) $role['activo']) {
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
                    $errorMessage = 'No se puede desactivar un rol asignado a usuarios activos. Reasigna esos usuarios primero.';
                }
            }

            if ($errorMessage === '') {
                $updateStatement = $pdo->prepare(
                    'UPDATE roles
                     SET nombre = :nombre, descripcion = :descripcion, activo = :activo
                     WHERE rol_id = :rol_id'
                );
                $updateStatement->execute([
                    'nombre' => $roleName,
                    'descripcion' => $description !== '' ? $description : null,
                    'activo' => $active ? 1 : 0,
                    'rol_id' => $roleId,
                ]);

                if (!$protectedRole) {
                    $deletePermissionsStatement = $pdo->prepare(
                        'DELETE FROM rol_permiso WHERE rol_id = :rol_id'
                    );
                    $deletePermissionsStatement->execute(['rol_id' => $roleId]);

                    $insertPermissionStatement = $pdo->prepare(
                        'INSERT INTO rol_permiso (rol_id, permiso_id) VALUES (:rol_id, :permiso_id)'
                    );
                    foreach ($selectedPermissionIds as $permissionId) {
                        $insertPermissionStatement->execute([
                            'rol_id' => $roleId,
                            'permiso_id' => $permissionId,
                        ]);
                    }
                }

                $pdo->commit();
                $_SESSION['roles_mensaje'] = 'Los cambios del rol se guardaron correctamente.';
                redirect_to('/assets/modules/roles/index.php');
            }
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Hotel Manager role update failed: ' . $exception->getMessage());
            $errorMessage = 'No se pudieron guardar los cambios. Verifica que el nombre del rol no esté duplicado.';
        }
    }
}

$baseUrl = htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8');
$tituloCabecera = 'Editar rol';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/../../includes/favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/estilos.css">
    <title>Editar rol | Hotel Premium</title>
</head>
<body class="panel">
    <div class="panel-panel">
        <?php require __DIR__ . '/../../includes/panel_cabecera.php'; ?>
        <div class="panel-distribucion">
            <?php require __DIR__ . '/../../includes/sidebar.php'; ?>
            <main class="panel-contenido pagina-roles">
                <header class="roles-cabecera">
                    <p>Edita los datos y permisos del rol.</p>
                    <a class="boton-secundario" href="<?= $baseUrl ?>/assets/modules/roles/index.php">Volver a roles</a>
                </header>

                <?php if ($protectedRole): ?>
                    <p class="mensaje-info">El rol Administrador está protegido: solo se puede modificar su descripción.</p>
                <?php endif; ?>
                <?php if ($errorMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <form class="formulario-rol" method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="rol_id" value="<?= (int) $roleId ?>">

                    <label for="nombre">Nombre del rol</label>
                    <input
                        id="nombre"
                        name="nombre"
                        type="text"
                        maxlength="50"
                        required
                        value="<?= htmlspecialchars($roleName, ENT_QUOTES, 'UTF-8') ?>"
                        <?= $protectedRole ? 'readonly' : '' ?>
                    >

                    <label for="descripcion">Descripción</label>
                    <textarea id="descripcion" name="descripcion" maxlength="150" rows="3"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></textarea>

                    <?php if (!$protectedRole): ?>
                        <input type="hidden" name="activo" value="0">
                        <label class="casilla-usuario" for="activo">
                            <input id="activo" name="activo" type="checkbox" value="1" <?= $active ? 'checked' : '' ?>>
                            Rol activo
                        </label>

                        <fieldset class="permisos-rol">
                            <legend>Permisos</legend>
                            <?php foreach ($permissions as $permission): ?>
                                <label class="casilla-permiso">
                                    <input
                                        type="checkbox"
                                        name="permission_ids[]"
                                        value="<?= (int) $permission['permiso_id'] ?>"
                                        <?= $selectedPermissionIds !== null && in_array((int) $permission['permiso_id'], $selectedPermissionIds, true) ? 'checked' : '' ?>
                                    >
                                    <span>
                                        <strong><?= htmlspecialchars($permission['nombre'], ENT_QUOTES, 'UTF-8') ?></strong>
                                        <?php if ($permission['descripcion'] !== null && $permission['descripcion'] !== ''): ?>
                                            <small><?= htmlspecialchars($permission['descripcion'], ENT_QUOTES, 'UTF-8') ?></small>
                                        <?php endif; ?>
                                    </span>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                    <?php endif; ?>

                    <button class="boton-accion" type="submit">Guardar cambios</button>
                </form>
            </main>
        </div>
    </div>
</body>
</html>