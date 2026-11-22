<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';
require_role($pdo, 'Administrador');

$statement = $pdo->query(
    'SELECT e.empleado_id, e.cargo, e.fecha_contratacion, e.salario, e.activo,
            p.nombres, p.apellidos, p.numero_documento, td.abreviatura
     FROM empleados e
     INNER JOIN personas p ON p.persona_id = e.persona_id
     INNER JOIN tipos_documentos td ON td.tipo_documento_id = p.tipo_documento_id
     ORDER BY e.activo DESC, p.apellidos, p.nombres'
);
$employees = $statement->fetchAll();
$successMessage = $_SESSION['empleados_mensaje'] ?? '';
$errorMessage = $_SESSION['empleados_error'] ?? '';
unset($_SESSION['empleados_mensaje'], $_SESSION['empleados_error']);
$baseUrl = htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8');
$tituloCabecera = 'Empleados';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/../../includes/favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/estilos.css">
    <title>Empleados | Hotel Premium</title>
</head>
<body class="panel">
    <div class="panel-panel">
        <?php require __DIR__ . '/../../includes/panel_cabecera.php'; ?>
        <div class="panel-distribucion">
            <?php require __DIR__ . '/../../includes/sidebar.php'; ?>
            <main class="panel-contenido pagina-empleados">
                <header class="empleados-cabecera">
                    <p>Consulta y administra los empleados del hotel.</p>
                    <a class="boton-accion" href="<?= $baseUrl ?>/assets/modules/empleados/agregar.php">
                        <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                        Agregar empleado
                    </a>
                </header>

                <?php if ($successMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-exito"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if ($errorMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <?php if ($employees === []): ?>
                    <p class="mensaje-info">No hay empleados registrados.</p>
                <?php else: ?>
                    <div class="tabla-contenedor">
                        <table class="tabla-empleados">
                            <thead>
                                <tr>
                                    <th>Nombre completo</th>
                                    <th>Documento</th>
                                    <th>Cargo</th>
                                    <th>Fecha de contratación</th>
                                    <th>Salario</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($employees as $employee): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($employee['nombres'] . ' ' . $employee['apellidos'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($employee['abreviatura'] . ' ' . $employee['numero_documento'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($employee['cargo'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($employee['fecha_contratacion'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= $employee['salario'] === null ? 'No indicado' : htmlspecialchars(number_format((float) $employee['salario'], 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <span class="estado-usuario <?= (bool) $employee['activo'] ? 'estado-activo' : 'estado-inactivo' ?>">
                                                <?= (bool) $employee['activo'] ? 'Activo' : 'Inactivo' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="acciones-empleado">
                                                <a
                                                    class="boton-secundario"
                                                    href="<?= $baseUrl ?>/assets/modules/empleados/editar.php?id=<?= (int) $employee['empleado_id'] ?>"
                                                >Editar</a>
                                                <?php if ((bool) $employee['activo']): ?>
                                                    <form
                                                        action="<?= $baseUrl ?>/assets/modules/empleados/eliminar.php"
                                                        method="post"
                                                        onsubmit="return confirm('¿Deseas desactivar este empleado?');"
                                                    >
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="empleado_id" value="<?= (int) $employee['empleado_id'] ?>">
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