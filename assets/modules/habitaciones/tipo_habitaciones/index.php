<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../config/database.php';
require_role($pdo, 'Administrador');

$statement = $pdo->query(
    'SELECT th.tipo_habitacion_id, th.nombre, th.capacidad, th.precio_noche, th.activo,
            COUNT(h.habitacion_id) AS habitaciones_asociadas
     FROM tipos_habitaciones th
     LEFT JOIN habitaciones h ON h.tipo_habitacion_id = th.tipo_habitacion_id
     GROUP BY th.tipo_habitacion_id, th.nombre, th.capacidad, th.precio_noche, th.activo
     ORDER BY th.nombre'
);
$roomTypes = $statement->fetchAll();
$successMessage = $_SESSION['tipos_habitaciones_mensaje'] ?? '';
$errorMessage = $_SESSION['tipos_habitaciones_error'] ?? '';
unset($_SESSION['tipos_habitaciones_mensaje'], $_SESSION['tipos_habitaciones_error']);
$baseUrl = htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8');
$tituloCabecera = 'Tipos de habitaciones';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/../../../includes/favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/estilos.css">
    <title>Tipos de habitaciones | Hotel Premium</title>
</head>
<body class="panel">
    <div class="panel-panel">
        <?php require __DIR__ . '/../../../includes/panel_cabecera.php'; ?>
        <div class="panel-distribucion">
            <?php require __DIR__ . '/../../../includes/sidebar.php'; ?>
            <main class="panel-contenido pagina-habitaciones">
                <header class="habitaciones-cabecera">
                    <p>Administra las capacidades y tarifas de los tipos de habitación.</p>
                    <a class="boton-accion" href="<?= $baseUrl ?>/assets/modules/habitaciones/tipo_habitaciones/crear.php">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        Crear tipo
                    </a>
                </header>

                <?php if ($successMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-exito"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if ($errorMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <?php if ($roomTypes === []): ?>
                    <p class="mensaje-info">No hay tipos de habitaciones registrados.</p>
                <?php else: ?>
                    <div class="tabla-contenedor">
                        <table class="tabla-registros">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Capacidad</th>
                                    <th>Precio por noche</th>
                                    <th>Habitaciones asociadas</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($roomTypes as $roomType): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($roomType['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= (int) $roomType['capacidad'] ?></td>
                                        <td><?= htmlspecialchars((string) $roomType['precio_noche'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= (int) $roomType['habitaciones_asociadas'] ?></td>
                                        <td>
                                            <span class="estado-usuario <?= (bool) $roomType['activo'] ? 'estado-activo' : 'estado-inactivo' ?>">
                                                <?= (bool) $roomType['activo'] ? 'Activo' : 'Inactivo' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="acciones-registro">
                                                <a
                                                    class="boton-secundario"
                                                    href="<?= $baseUrl ?>/assets/modules/habitaciones/tipo_habitaciones/editar.php?id=<?= (int) $roomType['tipo_habitacion_id'] ?>"
                                                >Editar</a>
                                                <?php if ((int) $roomType['habitaciones_asociadas'] === 0): ?>
                                                    <form
                                                        action="<?= $baseUrl ?>/assets/modules/habitaciones/tipo_habitaciones/eliminar.php"
                                                        method="post"
                                                        onsubmit="return confirm('¿Deseas eliminar este tipo de habitación?');"
                                                    >
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="tipo_habitacion_id" value="<?= (int) $roomType['tipo_habitacion_id'] ?>">
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