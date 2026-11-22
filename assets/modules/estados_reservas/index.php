<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';
require_role($pdo, 'Administrador');

$statement = $pdo->query(
    'SELECT er.estado_reserva_id, er.nombre, er.descripcion, er.activo,
            COUNT(r.reserva_id) AS reservas_asociadas
     FROM estados_reservas er
     LEFT JOIN reservas r ON r.estado_reserva_id = er.estado_reserva_id
     GROUP BY er.estado_reserva_id, er.nombre, er.descripcion, er.activo
     ORDER BY er.nombre'
);
$reservationStates = $statement->fetchAll();
$successMessage = $_SESSION['estados_reservas_mensaje'] ?? '';
$errorMessage = $_SESSION['estados_reservas_error'] ?? '';
unset($_SESSION['estados_reservas_mensaje'], $_SESSION['estados_reservas_error']);
$baseUrl = htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8');
$tituloCabecera = 'Estados de reservas';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/../../includes/favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/estilos.css">
    <title>Estados de reservas | Hotel Premium</title>
</head>
<body class="panel">
    <div class="panel-panel">
        <?php require __DIR__ . '/../../includes/panel_cabecera.php'; ?>
        <div class="panel-distribucion">
            <?php require __DIR__ . '/../../includes/sidebar.php'; ?>
            <main class="panel-contenido pagina-habitaciones">
                <header class="habitaciones-cabecera">
                    <p>Administra los estados y su disponibilidad para las reservas.</p>
                    <a class="boton-accion" href="<?= $baseUrl ?>/assets/modules/estados_reservas/crear.php">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        Crear estado
                    </a>
                </header>

                <?php if ($successMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-exito"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if ($errorMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <?php if ($reservationStates === []): ?>
                    <p class="mensaje-info">No hay estados de reserva registrados.</p>
                <?php else: ?>
                    <div class="tabla-contenedor">
                        <table class="tabla-registros">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Descripción</th>
                                    <th>Reservas asociadas</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservationStates as $reservationState): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($reservationState['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($reservationState['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= (int) $reservationState['reservas_asociadas'] ?></td>
                                        <td>
                                            <span class="estado-usuario <?= (bool) $reservationState['activo'] ? 'estado-activo' : 'estado-inactivo' ?>">
                                                <?= (bool) $reservationState['activo'] ? 'Activo' : 'Inactivo' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="acciones-registro">
                                                <a
                                                    class="boton-secundario"
                                                    href="<?= $baseUrl ?>/assets/modules/estados_reservas/editar.php?id=<?= (int) $reservationState['estado_reserva_id'] ?>"
                                                >Editar</a>
                                                <?php if (in_array($reservationState['nombre'], ['Pendiente', 'Confirmada', 'Cancelada'], true)): ?>
                                                    <span class="ayuda-formulario">Estado requerido por el flujo de reservas</span>
                                                <?php elseif ((bool) $reservationState['activo']): ?>
                                                    <form
                                                        action="<?= $baseUrl ?>/assets/modules/estados_reservas/cancelar.php"
                                                        method="post"
                                                        onsubmit="return confirm('¿Deseas desactivar este estado? Las reservas existentes conservarán su historial.');"
                                                    >
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="estado_reserva_id" value="<?= (int) $reservationState['estado_reserva_id'] ?>">
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
