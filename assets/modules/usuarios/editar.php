<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/helpers.php';
require_role($pdo, 'Administrador');

$userIdInput = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['usuario_id'] ?? '')
    : ($_GET['id'] ?? '');
$userId = filter_var($userIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($userId === false) {
    http_response_code(400);
    exit('ID de usuario inválido.');
}

$userStatement = $pdo->prepare(
    'SELECT usuario_id, persona_id, nombre_usuario, email, activo
     FROM usuarios
     WHERE usuario_id = :usuario_id
     LIMIT 1'
);
$userStatement->execute(['usuario_id' => $userId]);
$user = $userStatement->fetch();
if (!$user) {
    http_response_code(404);
    exit('No se encontró el usuario solicitado.');
}

$userRolesStatement = $pdo->prepare(
    'SELECT rol_id FROM usuario_rol WHERE usuario_id = :usuario_id'
);
$userRolesStatement->execute(['usuario_id' => $userId]);
$currentRoleIds = array_map('intval', array_column($userRolesStatement->fetchAll(), 'rol_id'));
$roles = usuarios_roles_activos($pdo);
$errorMessage = '';
$username = $user['nombre_usuario'];
$email = $user['email'];
$personaIdInput = (string) $user['persona_id'];
$active = (bool) $user['activo'];
$selectedRoleIds = $currentRoleIds;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Solicitud inválida. Vuelve a cargar el formulario e inténtalo de nuevo.');
    }

    $personaIdInput = trim((string) ($_POST['persona_id'] ?? ''));
    $username = trim((string) ($_POST['nombre_usuario'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $activeInput = $_POST['activo'] ?? '0';
    $active = $activeInput === '1';
    $selectedRoleIds = usuarios_parsear_roles($_POST['role_ids'] ?? []);
    $personaId = filter_var(
        $personaIdInput,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($personaId === false || $username === '' || strlen($username) > 50
        || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100
        || ($password !== '' && strlen($password) < 12)
        || !in_array($activeInput, ['0', '1'], true)
        || $selectedRoleIds === null || !usuarios_roles_validos($pdo, $selectedRoleIds)) {
        $errorMessage = 'Verifica el ID de persona, el usuario, el correo, la contraseña (mínimo 12 caracteres si se cambia) y los roles seleccionados.';
    } else {
        $adminRoleId = usuarios_id_rol_administrador($pdo);
        $isAdmin = $adminRoleId !== null && in_array($adminRoleId, $currentRoleIds, true);
        $keepsAdminRole = $adminRoleId !== null && in_array($adminRoleId, $selectedRoleIds, true);
        $isEditingSelf = (int) $userId === (int) $_SESSION['usuario_id'];

        if ($isEditingSelf && (!$keepsAdminRole || !$active)) {
            $errorMessage = 'No puedes desactivar tu propia cuenta ni quitarte el rol de administrador.';
        } else {
            try {
                $pdo->beginTransaction();
                if ($isAdmin && (!$keepsAdminRole || !$active)) {
                    $adminLockStatement = $pdo->prepare(
                        'SELECT u.usuario_id
                         FROM usuarios u
                         INNER JOIN usuario_rol ur ON ur.usuario_id = u.usuario_id
                         INNER JOIN roles r ON r.rol_id = ur.rol_id
                         WHERE u.activo = 1 AND r.activo = 1 AND r.nombre = :nombre
                         FOR UPDATE'
                    );
                    $adminLockStatement->execute(['nombre' => 'Administrador']);
                    if (count($adminLockStatement->fetchAll()) <= 1) {
                        $pdo->rollBack();
                        $errorMessage = 'No puedes quitar el rol ni desactivar al último administrador activo.';
                    }
                }

                if ($errorMessage === '') {
                    if ($password === '') {
                        $updateStatement = $pdo->prepare(
                            'UPDATE usuarios
                             SET persona_id = :persona_id, nombre_usuario = :nombre_usuario,
                                 email = :email, activo = :activo
                             WHERE usuario_id = :usuario_id'
                        );
                        $updateStatement->execute([
                            'persona_id' => $personaId,
                            'nombre_usuario' => $username,
                            'email' => $email,
                            'activo' => $active ? 1 : 0,
                            'usuario_id' => $userId,
                        ]);
                    } else {
                        $updateStatement = $pdo->prepare(
                            'UPDATE usuarios
                             SET persona_id = :persona_id, nombre_usuario = :nombre_usuario,
                                 email = :email, password_hash = :password_hash, activo = :activo
                             WHERE usuario_id = :usuario_id'
                        );
                        $updateStatement->execute([
                            'persona_id' => $personaId,
                            'nombre_usuario' => $username,
                            'email' => $email,
                            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                            'activo' => $active ? 1 : 0,
                            'usuario_id' => $userId,
                        ]);
                    }

                    $deleteRolesStatement = $pdo->prepare(
                        'DELETE FROM usuario_rol WHERE usuario_id = :usuario_id'
                    );
                    $deleteRolesStatement->execute(['usuario_id' => $userId]);

                    $insertRoleStatement = $pdo->prepare(
                        'INSERT INTO usuario_rol (usuario_id, rol_id) VALUES (:usuario_id, :rol_id)'
                    );
                    foreach ($selectedRoleIds as $roleId) {
                        $insertRoleStatement->execute(['usuario_id' => $userId, 'rol_id' => $roleId]);
                    }

                    $pdo->commit();
                    $_SESSION['usuarios_mensaje'] = 'Los cambios del usuario se guardaron correctamente.';
                    redirect_to('/assets/modules/usuarios/index.php');
                }
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Hotel Manager user update failed: ' . $exception->getMessage());
                $errorMessage = 'No se pudieron guardar los cambios. Verifica que el ID de persona, el nombre de usuario y el correo sean válidos y no estén duplicados.';
            }
        }
    }
}

$baseUrl = htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8');
$tituloCabecera = 'Editar usuario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/estilos.css">
    <title>Editar usuario | Hotel Premium</title>
</head>
<body class="panel">
    <div class="panel-panel">
        <?php require __DIR__ . '/../../includes/panel_cabecera.php'; ?>
        <div class="panel-distribucion">
            <?php require __DIR__ . '/../../includes/sidebar.php'; ?>
            <main class="panel-contenido pagina-usuarios">
                <header class="usuarios-cabecera">
                    <p>Actualiza los datos y los roles asignados.</p>
                    <a class="boton-secundario" href="<?= $baseUrl ?>/assets/modules/usuarios/index.php">Volver a usuarios</a>
                </header>

                <?php if ($errorMessage !== ''): ?>
                    <p class="mensaje-usuarios mensaje-error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <form class="formulario-usuario" method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="usuario_id" value="<?= (int) $userId ?>">

                    <label for="persona_id">ID de persona</label>
                    <input id="persona_id" name="persona_id" type="number" min="1" required value="<?= htmlspecialchars($personaIdInput, ENT_QUOTES, 'UTF-8') ?>">

                    <label for="nombre_usuario">Nombre de usuario</label>
                    <input id="nombre_usuario" name="nombre_usuario" type="text" maxlength="50" required value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>">

                    <label for="email">Correo electrónico</label>
                    <input id="email" name="email" type="email" maxlength="100" required value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">

                    <input type="hidden" name="activo" value="0">
                    <label class="casilla-usuario" for="activo">
                        <input id="activo" name="activo" type="checkbox" value="1" <?= $active ? 'checked' : '' ?>>
                        Cuenta activa
                    </label>

                    <label for="password">Nueva contraseña (opcional, mínimo 12 caracteres)</label>
                    <input id="password" name="password" type="password" minlength="12" autocomplete="new-password">

                    <label for="role_ids">Roles</label>
                    <select id="role_ids" name="role_ids[]" multiple size="<?= max(2, min(6, count($roles))) ?>" required>
                        <?php foreach ($roles as $role): ?>
                            <option
                                value="<?= (int) $role['rol_id'] ?>"
                                <?= $selectedRoleIds !== null && in_array((int) $role['rol_id'], $selectedRoleIds, true) ? 'selected' : '' ?>
                            ><?= htmlspecialchars($role['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="ayuda-formulario">Mantén presionada Ctrl (Windows) o Cmd (Mac) para seleccionar varios roles.</p>

                    <button class="boton-accion" type="submit">Guardar cambios</button>
                </form>
            </main>
        </div>
    </div>
</body>
</html>