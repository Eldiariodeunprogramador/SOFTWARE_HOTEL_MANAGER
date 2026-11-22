<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';
require_role($pdo, 'Administrador');

$statement = $pdo->query(
    'SELECT u.usuario_id, u.persona_id, u.nombre_usuario, u.email, u.activo,
            GROUP_CONCAT(DISTINCT r.nombre ORDER BY r.nombre SEPARATOR \', \') AS roles
     FROM usuarios u
     LEFT JOIN usuario_rol ur ON ur.usuario_id = u.usuario_id
     LEFT JOIN roles r ON r.rol_id = ur.rol_id
     GROUP BY u.usuario_id, u.persona_id, u.nombre_usuario, u.email, u.activo
     ORDER BY u.nombre_usuario'
);
$users = $statement->fetchAll();
$successMessage = $_SESSION['usuarios_mensaje'] ?? '';
$errorMessage = $_SESSION['usuarios_error'] ?? '';
unset($_SESSION['usuarios_mensaje'], $_SESSION['usuarios_error']);
$baseUrl = htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8');
$tituloCabecera = 'Usuarios';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/../../includes/favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/estilos.css">
    <title>Usuarios | Hotel Premium</title>
</head>
<body class="panel">
    <div class="panel-panel">
        <?php require __DIR__ . '/../../includes/panel_cabecera.php'; ?>
        <div class="panel-distribucion">
            <?php require __DIR__ . '/../../includes/sidebar.php'; ?>
            <main class="panel-contenido pagina-usuarios">
                <header class="usuarios-cabecera">
                    <p>Consulta y administra las cuentas del sistema.</p>
                    <a class="boton-accion" href="<?= $baseUrl ?>/assets/modules/usuarios/agregar.php">
                        <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                        Agregar usuario
                    </a>
                </header>

                <?php if ($successMessage !== ''): ?>
                    <p class="mensaje-usuarios mensaje-exito"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if ($errorMessage !== ''): ?>
                    <p class="mensaje-usuarios mensaje-error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <?php if ($users === []): ?>
                    <p class="mensaje-info">No hay usuarios registrados.</p>
                <?php else: ?>
                    <div class="tabla-contenedor">
                        <table class="tabla-usuarios">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Persona ID</th>
                                    <th>Correo</th>
                                    <th>Rol(es)</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($user['nombre_usuario'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars((string) $user['persona_id'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($user['roles'] ?? 'Sin rol asignado', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <span class="estado-usuario <?= (bool) $user['activo'] ? 'estado-activo' : 'estado-inactivo' ?>">
                                                <?= (bool) $user['activo'] ? 'Activo' : 'Inactivo' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="acciones-usuario">
                                                <a
                                                    class="boton-secundario"
                                                    href="<?= $baseUrl ?>/assets/modules/usuarios/editar.php?id=<?= (int) $user['usuario_id'] ?>"
                                                >Editar</a>
                                                <?php if ((int) $user['usuario_id'] !== (int) $_SESSION['usuario_id'] && (bool) $user['activo']): ?>
                                                    <form
                                                        action="<?= $baseUrl ?>/assets/modules/usuarios/eliminar.php"
                                                        method="post"
                                                        onsubmit="return confirm('¿Deseas desactivar este usuario?');"
                                                    >
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="usuario_id" value="<?= (int) $user['usuario_id'] ?>">
                                                        <button class="boton-peligro" type="submit">Desactivar</button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>