<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';
require_role($pdo, 'Administrador');

$statement = $pdo->query(
    'SELECT h.habitacion_id, h.numero, h.piso, h.descripcion,
            th.nombre AS tipo, th.capacidad, th.precio_noche,
            eh.nombre AS estado, COUNT(r.reserva_id) AS reservas
     FROM habitaciones h
     INNER JOIN tipos_habitaciones th ON th.tipo_habitacion_id = h.tipo_habitacion_id
     INNER JOIN estados_habitaciones eh ON eh.estado_habitacion_id = h.estado_habitacion_id
     LEFT JOIN reservas r ON r.habitacion_id = h.habitacion_id
     GROUP BY h.habitacion_id, h.numero, h.piso, h.descripcion,
              th.nombre, th.capacidad, th.precio_noche, eh.nombre
     ORDER BY h.numero'
);
$rooms = $statement->fetchAll();
$successMessage = $_SESSION['habitaciones_mensaje'] ?? '';
$errorMessage = $_SESSION['habitaciones_error'] ?? '';
unset($_SESSION['habitaciones_mensaje'], $_SESSION['habitaciones_error']);
$baseUrl = htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8');
$tituloCabecera = 'Habitaciones';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/../../includes/favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/estilos.css">
    <title>Habitaciones | Hotel Premium</title>
</head>
<body class="panel">
    <div class="panel-panel">
        <?php require __DIR__ . '/../../includes/panel_cabecera.php'; ?>
        <div class="panel-distribucion">
            <?php require __DIR__ . '/../../includes/sidebar.php'; ?>
            <main class="panel-contenido pagina-habitaciones">
                <header class="habitaciones-cabecera">
                    <p>Consulta y administra las habitaciones del hotel.</p>
                    <a class="boton-accion" href="<?= $baseUrl ?>/assets/modules/habitaciones/crear.php">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        Crear habitación
                    </a>
                </header>

                <?php if ($successMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-exito"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if ($errorMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <?php if ($rooms === []): ?>
                    <p class="mensaje-info">No hay habitaciones registradas.</p>
                <?php else: ?>
                    <div class="tabla-contenedor">
                        <table class="tabla-habitaciones">
                            <thead>
                                <tr>
                                    <th>Número</th>
                                    <th>Tipo</th>
                                    <th>Capacidad</th>
                                    <th>Precio por noche</th>
                                    <th>Estado</th>
                                    <th>Piso</th>
                                    <th>Descripción</th>
                                    <th>Reservas</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($rooms as $room): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($room['numero'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($room['tipo'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= (int) $room['capacidad'] ?></td>
                                        <td><?= htmlspecialchars((string) $room['precio_noche'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($room['estado'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($room['piso'] === null ? 'No indicado' : (string) $room['piso'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($room['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= (int) $room['reservas'] ?></td>
                                        <td>
                                            <div class="acciones-habitacion">
                                                <a
                                                    class="boton-secundario"
                                                    href="<?= $baseUrl ?>/assets/modules/habitaciones/editar.php?id=<?= (int) $room['habitacion_id'] ?>"
                                                >Editar</a>
                                                <?php if ((int) $room['reservas'] === 0): ?>
                                                    <form
                                                        action="<?= $baseUrl ?>/assets/modules/habitaciones/eliminar.php"
                                                        method="post"
                                                        onsubmit="return confirm('¿Deseas eliminar esta habitación?');"
                                                    >
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="habitacion_id" value="<?= (int) $room['habitacion_id'] ?>">
                                                        <button class="boton-peligro" type="submit">Eliminar</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="ayuda-formulario">Tiene reservas</span>
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