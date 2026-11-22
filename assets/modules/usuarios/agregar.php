<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/helpers.php';
require_role($pdo, 'Administrador');

$roles = usuarios_roles_activos($pdo);
$errorMessage = '';
$personaIdInput = '';
$username = '';
$email = '';
$selectedRoleIds = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Solicitud inválida. Vuelve a cargar el formulario e inténtalo de nuevo.');
    }

    $personaIdInput = trim((string) ($_POST['persona_id'] ?? ''));
    $username = trim((string) ($_POST['nombre_usuario'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $selectedRoleIds = usuarios_parsear_roles($_POST['role_ids'] ?? []);
    $personaId = filter_var(
        $personaIdInput,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($personaId === false || $username === '' || strlen($username) > 50
        || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100
        || strlen($password) < 12 || $selectedRoleIds === null
        || !usuarios_roles_validos($pdo, $selectedRoleIds)) {
        $errorMessage = 'Verifica el ID de persona, el usuario, el correo, la contraseña (mínimo 12 caracteres) y los roles seleccionados.';
    } else {
        try {
            $pdo->beginTransaction();
            $userStatement = $pdo->prepare(
                'INSERT INTO usuarios (persona_id, nombre_usuario, email, password_hash, activo)
                 VALUES (:persona_id, :nombre_usuario, :email, :password_hash, 1)'
            );
            $userStatement->execute([
                'persona_id' => $personaId,
                'nombre_usuario' => $username,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            $userId = (int) $pdo->lastInsertId();

            $roleStatement = $pdo->prepare(
                'INSERT INTO usuario_rol (usuario_id, rol_id) VALUES (:usuario_id, :rol_id)'
            );
            foreach ($selectedRoleIds as $roleId) {
                $roleStatement->execute(['usuario_id' => $userId, 'rol_id' => $roleId]);
            }

            $pdo->commit();
            $_SESSION['usuarios_mensaje'] = 'El usuario se agregó correctamente.';
            redirect_to('/assets/modules/usuarios/index.php');
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Hotel Manager user creation failed: ' . $exception->getMessage());
            $errorMessage = 'No se pudo agregar el usuario. Verifica que el ID de persona, el nombre de usuario y el correo sean válidos y no estén duplicados.';
        }
    }
}

$baseUrl = htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8');
$tituloCabecera = 'Agregar usuario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/estilos.css">
    <title>Agregar usuario | Hotel Premium</title>
</head>
<body class="panel">
    <div class="panel-panel">
        <?php require __DIR__ . '/../../includes/panel_cabecera.php'; ?>
        <div class="panel-distribucion">
            <?php require __DIR__ . '/../../includes/sidebar.php'; ?>
            <main class="panel-contenido pagina-usuarios">
                <header class="usuarios-cabecera">
                    <p>Registra una cuenta y asigna uno o más roles.</p>
                    <a class="boton-secundario" href="<?= $baseUrl ?>/assets/modules/usuarios/index.php">Volver a usuarios</a>
                </header>

                <?php if ($errorMessage !== ''): ?>
                    <p class="mensaje-usuarios mensaje-error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <form class="formulario-usuario" method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <label for="persona_id">ID de persona</label>
                    <input id="persona_id" name="persona_id" type="number" min="1" required value="<?= htmlspecialchars($personaIdInput, ENT_QUOTES, 'UTF-8') ?>">

                    <label for="nombre_usuario">Nombre de usuario</label>
                    <input id="nombre_usuario" name="nombre_usuario" type="text" maxlength="50" required value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>">

                    <label for="email">Correo electrónico</label>
                    <input id="email" name="email" type="email" maxlength="100" required value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>">

                    <label for="password">Contraseña (mínimo 12 caracteres)</label>
                    <input id="password" name="password" type="password" minlength="12" required autocomplete="new-password">

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

                    <button class="boton-accion" type="submit">Guardar usuario</button>
                </form>
            </main>
        </div>
    </div>
</body>
</html>