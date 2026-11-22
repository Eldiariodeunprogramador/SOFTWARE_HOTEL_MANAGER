<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/helpers.php';
require_role($pdo, 'Administrador');

$roles = usuarios_roles_activos($pdo);
$personsStatement = $pdo->query(
    'SELECT p.persona_id, p.nombres, p.apellidos,
            td.abreviatura, p.numero_documento
     FROM personas p
     INNER JOIN tipos_documentos td ON td.tipo_documento_id = p.tipo_documento_id
     LEFT JOIN usuarios u ON u.persona_id = p.persona_id
     WHERE u.usuario_id IS NULL
     ORDER BY p.apellidos, p.nombres'
);
$personas = $personsStatement->fetchAll();
$availablePersonaIds = array_map('intval', array_column($personas, 'persona_id'));
$registeredUsersStatement = $pdo->query(
    'SELECT u.usuario_id, u.nombre_usuario, u.email, u.activo,
            p.nombres, p.apellidos,
            GROUP_CONCAT(DISTINCT r.nombre ORDER BY r.nombre SEPARATOR \', \') AS roles
     FROM usuarios u
     INNER JOIN personas p ON p.persona_id = u.persona_id
     LEFT JOIN usuario_rol ur ON ur.usuario_id = u.usuario_id
     LEFT JOIN roles r ON r.rol_id = ur.rol_id
     GROUP BY u.usuario_id, u.nombre_usuario, u.email, u.activo, p.nombres, p.apellidos
     ORDER BY p.apellidos, p.nombres'
);
$registeredUsers = $registeredUsersStatement->fetchAll();
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

    $personaInput = $_POST['persona_id'] ?? '';
    $usernameInput = $_POST['nombre_usuario'] ?? '';
    $emailInput = $_POST['email'] ?? '';
    $passwordInput = $_POST['password'] ?? '';
    $invalidInput = !is_string($personaInput) || !is_string($usernameInput)
        || !is_string($emailInput) || !is_string($passwordInput);
    $personaIdInput = is_string($personaInput) ? trim($personaInput) : '';
    $username = is_string($usernameInput) ? trim($usernameInput) : '';
    $email = is_string($emailInput) ? trim($emailInput) : '';
    $password = is_string($passwordInput) ? $passwordInput : '';
    $selectedRoleIds = usuarios_parsear_roles($_POST['role_ids'] ?? []);
    $personaId = filter_var(
        $personaIdInput,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($invalidInput || $personaId === false
        || !in_array((int) $personaId, $availablePersonaIds, true)
        || $username === '' || strlen($username) > 50
        || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100
        || strlen($password) < 12 || $selectedRoleIds === null
        || !usuarios_roles_validos($pdo, $selectedRoleIds)) {
        $errorMessage = 'Selecciona una persona disponible y verifica el usuario, el correo, la contraseña (mínimo 12 caracteres) y los roles.';
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
            $errorMessage = 'No se pudo agregar el usuario. Verifica que la persona no tenga ya una cuenta y que el nombre de usuario y el correo no estén duplicados.';
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
    <?php require __DIR__ . '/../../includes/favicon.php'; ?>
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

                <?php if ($personas === []): ?>
                    <p class="mensaje-info">
                        No hay personas disponibles para crear otra cuenta.
                        <a href="<?= $baseUrl ?>/assets/modules/personas/agregar.php">Registra una persona</a> primero.
                    </p>
                <?php endif; ?>

                <form class="formulario-usuario" method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <label for="persona_id">Persona</label>
                    <select id="persona_id" name="persona_id" required <?= $personas === [] ? 'disabled' : '' ?>>
                        <?php if ($personas === []): ?>
                            <option value="">No hay personas disponibles</option>
                        <?php else: ?>
                            <option value="">Selecciona una persona</option>
                            <?php foreach ($personas as $persona): ?>
                                <option
                                    value="<?= (int) $persona['persona_id'] ?>"
                                    <?= (string) $persona['persona_id'] === $personaIdInput ? 'selected' : '' ?>
                                ><?= htmlspecialchars($persona['nombres'] . ' ' . $persona['apellidos'] . ' - ' . $persona['abreviatura'] . ' ' . $persona['numero_documento'], ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>

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

                    <button class="boton-accion" type="submit" <?= $personas === [] ? 'disabled' : '' ?>>Guardar usuario</button>
                </form>

                <section class="usuarios-registrados" aria-labelledby="usuarios-registrados-titulo">
                    <h2 id="usuarios-registrados-titulo">Usuarios registrados</h2>
                    <?php if ($registeredUsers === []): ?>
                        <p class="mensaje-info">Todavía no hay usuarios registrados.</p>
                    <?php else: ?>
                        <div class="tabla-contenedor">
                            <table class="tabla-usuarios">
                                <thead>
                                    <tr>
                                        <th>Persona</th>
                                        <th>Usuario</th>
                                        <th>Correo</th>
                                        <th>Rol(es)</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($registeredUsers as $registeredUser): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($registeredUser['nombres'] . ' ' . $registeredUser['apellidos'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($registeredUser['nombre_usuario'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($registeredUser['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= htmlspecialchars($registeredUser['roles'] ?? 'Sin rol asignado', ENT_QUOTES, 'UTF-8') ?></td>
                                            <td><?= (bool) $registeredUser['activo'] ? 'Activo' : 'Inactivo' ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                    <a class="boton-secundario" href="<?= $baseUrl ?>/assets/modules/usuarios/index.php">Administrar usuarios</a>
                </section>
            </main>
        </div>
    </div>
</body>
</html>