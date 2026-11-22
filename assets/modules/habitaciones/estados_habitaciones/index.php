<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../config/database.php';
require_role($pdo, 'Administrador');

$statement = $pdo->query(
    'SELECT eh.estado_habitacion_id, eh.nombre,
            COUNT(h.habitacion_id) AS habitaciones_asociadas
     FROM estados_habitaciones eh
     LEFT JOIN habitaciones h ON h.estado_habitacion_id = eh.estado_habitacion_id
     GROUP BY eh.estado_habitacion_id, eh.nombre
     ORDER BY eh.nombre'
);
$roomStates = $statement->fetchAll();
$successMessage = $_SESSION['estados_habitaciones_mensaje'] ?? '';
$errorMessage = $_SESSION['estados_habitaciones_error'] ?? '';
unset($_SESSION['estados_habitaciones_mensaje'], $_SESSION['estados_habitaciones_error']);
$baseUrl = htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8');
$tituloCabecera = 'Estados de habitaciones';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/../../../includes/favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/estilos.css">
    <title>Estados de habitaciones | Hotel Premium</title>
</head>
<body class="panel">
    <div class="panel-panel">
        <?php require __DIR__ . '/../../../includes/panel_cabecera.php'; ?>
        <div class="panel-distribucion">
            <?php require __DIR__ . '/../../../includes/sidebar.php'; ?>
            <main class="panel-contenido pagina-habitaciones">
                <header class="habitaciones-cabecera">
                    <p>Administra los estados disponibles para las habitaciones.</p>
                    <a class="boton-accion" href="<?= $baseUrl ?>/assets/modules/habitaciones/estados_habitaciones/crear.php">
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

                <?php if ($roomStates === []): ?>
                    <p class="mensaje-info">No hay estados de habitación registrados.</p>
                <?php else: ?>
                    <div class="tabla-contenedor">
                        <table class="tabla-registros">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Habitaciones asociadas</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($roomStates as $roomState): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($roomState['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= (int) $roomState['habitaciones_asociadas'] ?></td>
                                        <td>
                                            <div class="acciones-registro">
                                                <a
                                                    class="boton-secundario"
                                                    href="<?= $baseUrl ?>/assets/modules/habitaciones/estados_habitaciones/editar.php?id=<?= (int) $roomState['estado_habitacion_id'] ?>"
                                                >Editar</a>
                                                <?php if ((int) $roomState['habitaciones_asociadas'] === 0): ?>
                                                    <form
                                                        action="<?= $baseUrl ?>/assets/modules/habitaciones/estados_habitaciones/eliminar.php"
                                                        method="post"
                                                        onsubmit="return confirm('¿Deseas eliminar este estado de habitación?');"
                                                    >
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="estado_habitacion_id" value="<?= (int) $roomState['estado_habitacion_id'] ?>">
                                                        <button class="boton-peligro" type="submit">Eliminar</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="ayuda-formulario">Tiene habitaciones asociadas</span>
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