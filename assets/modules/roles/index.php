<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';
require_permission('roles.ver');

$statement = $pdo->query(
    'SELECT r.rol_id, r.nombre, r.descripcion, r.activo,
            COUNT(DISTINCT rp.permiso_id) AS cantidad_permisos,
            COUNT(DISTINCT CASE WHEN u.activo = 1 THEN u.usuario_id END) AS usuarios_activos
     FROM roles r
     LEFT JOIN rol_permiso rp ON rp.rol_id = r.rol_id
     LEFT JOIN usuario_rol ur ON ur.rol_id = r.rol_id
     LEFT JOIN usuarios u ON u.usuario_id = ur.usuario_id
     GROUP BY r.rol_id, r.nombre, r.descripcion, r.activo
     ORDER BY r.nombre'
);
$roles = $statement->fetchAll();
$successMessage = $_SESSION['roles_mensaje'] ?? '';
$errorMessage = $_SESSION['roles_error'] ?? '';
unset($_SESSION['roles_mensaje'], $_SESSION['roles_error']);
$canEditRoles = user_has_permission($pdo, 'roles.editar');
$canDeleteRoles = user_has_permission($pdo, 'roles.eliminar');
$baseUrl = htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8');
$tituloCabecera = 'Roles';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/estilos.css">
    <title>Roles | Hotel Premium</title>
</head>
<body class="panel">
    <div class="panel-panel">
        <?php require __DIR__ . '/../../includes/panel_cabecera.php'; ?>
        <div class="panel-distribucion">
            <?php require __DIR__ . '/../../includes/sidebar.php'; ?>
            <main class="panel-contenido pagina-roles">
                <header class="roles-cabecera">
                    <p>Consulta los roles y los permisos asignados.</p>
                </header>

                <?php if ($successMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-exito"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if ($errorMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <?php if ($roles === []): ?>
                    <p class="mensaje-info">No hay roles registrados.</p>
                <?php else: ?>
                    <div class="tabla-contenedor">
                        <table class="tabla-registros">
                            <thead>
                                <tr>
                                    <th>Rol</th>
                                    <th>Descripción</th>
                                    <th>Permisos</th>
                                    <th>Usuarios activos</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($roles as $role): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($role['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($role['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= (int) $role['cantidad_permisos'] ?></td>
                                        <td><?= (int) $role['usuarios_activos'] ?></td>
                                        <td>
                                            <span class="estado-usuario <?= (bool) $role['activo'] ? 'estado-activo' : 'estado-inactivo' ?>">
                                                <?= (bool) $role['activo'] ? 'Activo' : 'Inactivo' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="acciones-registro">
                                                <?php if ($canEditRoles): ?>
                                                    <a
                                                        class="boton-secundario"
                                                        href="<?= $baseUrl ?>/assets/modules/roles/editar.php?id=<?= (int) $role['rol_id'] ?>"
                                                    >Editar</a>
                                                <?php endif; ?>
                                                <?php if ($canDeleteRoles && $role['nombre'] !== 'Administrador' && (bool) $role['activo']): ?>
                                                    <form
                                                        action="<?= $baseUrl ?>/assets/modules/roles/eliminar.php"
                                                        method="post"
                                                        onsubmit="return confirm('¿Deseas desactivar este rol?');"
                                                    >
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="rol_id" value="<?= (int) $role['rol_id'] ?>">
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