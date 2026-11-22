<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('reservas.ver');
require_once __DIR__ . '/../../config/database.php';

$statement = $pdo->query(
    'SELECT r.reserva_id, r.fecha_reserva, r.fecha_entrada, r.fecha_salida,
            r.numero_huespedes, r.precio_noche, r.observaciones,
            CONCAT(pc.nombres, \' \', pc.apellidos) AS cliente,
            h.numero AS habitacion,
            CONCAT(pe.nombres, \' \', pe.apellidos) AS empleado,
            er.nombre AS estado
     FROM reservas r
     INNER JOIN clientes c ON c.cliente_id = r.cliente_id
     INNER JOIN personas pc ON pc.persona_id = c.persona_id
     INNER JOIN habitaciones h ON h.habitacion_id = r.habitacion_id
     INNER JOIN estados_reservas er ON er.estado_reserva_id = r.estado_reserva_id
     LEFT JOIN empleados e ON e.empleado_id = r.empleado_id
     LEFT JOIN personas pe ON pe.persona_id = e.persona_id
     ORDER BY r.fecha_entrada DESC, r.reserva_id DESC'
);
$reservations = $statement->fetchAll();
$successMessage = $_SESSION['reservas_mensaje'] ?? '';
$errorMessage = $_SESSION['reservas_error'] ?? '';
unset($_SESSION['reservas_mensaje'], $_SESSION['reservas_error']);
$canCreate = user_has_permission($pdo, 'reservas.crear');
$canEdit = user_has_permission($pdo, 'reservas.editar');
$canCancel = user_has_permission($pdo, 'reservas.cancelar');
$baseUrl = htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8');
$tituloCabecera = 'Reservas';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/../../includes/favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/estilos.css">
    <title>Reservas | Hotel Premium</title>
</head>
<body class="panel">
    <div class="panel-panel">
        <?php require __DIR__ . '/../../includes/panel_cabecera.php'; ?>
        <div class="panel-distribucion">
            <?php require __DIR__ . '/../../includes/sidebar.php'; ?>
            <main class="panel-contenido pagina-habitaciones">
                <header class="habitaciones-cabecera">
                    <p>Consulta y administra las reservas, clientes y habitaciones.</p>
                    <?php if ($canCreate): ?>
                        <a class="boton-accion" href="<?= $baseUrl ?>/assets/modules/reservas/crear.php">
                            <i class="fa-solid fa-plus" aria-hidden="true"></i>
                            Crear reserva
                        </a>
                    <?php endif; ?>
                </header>

                <?php if ($successMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-exito"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if ($errorMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <?php if ($reservations === []): ?>
                    <p class="mensaje-info">No hay reservas registradas.</p>
                <?php else: ?>
                    <div class="tabla-contenedor">
                        <table class="tabla-habitaciones">
                            <thead>
                                <tr>
                                    <th>Cliente</th>
                                    <th>Habitación</th>
                                    <th>Entrada</th>
                                    <th>Salida</th>
                                    <th>Huéspedes</th>
                                    <th>Precio por noche</th>
                                    <th>Empleado</th>
                                    <th>Estado</th>
                                    <th>Observaciones</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservations as $reservation): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($reservation['cliente'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($reservation['habitacion'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($reservation['fecha_entrada'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($reservation['fecha_salida'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= (int) $reservation['numero_huespedes'] ?></td>
                                        <td><?= htmlspecialchars((string) $reservation['precio_noche'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($reservation['empleado'] ?? 'No asignado', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($reservation['estado'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($reservation['observaciones'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <div class="acciones-habitacion">
                                                <?php if ($canEdit && !in_array($reservation['estado'], ['Cancelada', 'Finalizada'], true)): ?>
                                                    <a
                                                        class="boton-secundario"
                                                        href="<?= $baseUrl ?>/assets/modules/reservas/editar.php?id=<?= (int) $reservation['reserva_id'] ?>"
                                                    >Editar</a>
                                                <?php endif; ?>
                                                <?php if ($canCancel && !in_array($reservation['estado'], ['Cancelada', 'Finalizada'], true)): ?>
                                                    <form
                                                        action="<?= $baseUrl ?>/assets/modules/reservas/cancelar.php"
                                                        method="post"
                                                        onsubmit="return confirm('¿Deseas cancelar esta reserva?');"
                                                    >
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="reserva_id" value="<?= (int) $reservation['reserva_id'] ?>">
                                                        <button class="boton-peligro" type="submit">Cancelar</button>
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