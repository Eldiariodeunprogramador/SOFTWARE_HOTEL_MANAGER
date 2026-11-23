<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';
require_role($pdo, 'Administrador');

$statement = $pdo->query(
    'SELECT ep.estado_pago_id, ep.nombre, ep.descripcion,
            COUNT(p.pago_id) AS pagos_asociados
     FROM estados_pagos ep
     LEFT JOIN pagos p ON p.estado_pago_id = ep.estado_pago_id
     GROUP BY ep.estado_pago_id, ep.nombre, ep.descripcion
     ORDER BY ep.nombre'
);
$paymentStates = $statement->fetchAll();
$successMessage = $_SESSION['estados_pagos_mensaje'] ?? '';
$errorMessage = $_SESSION['estados_pagos_error'] ?? '';
unset($_SESSION['estados_pagos_mensaje'], $_SESSION['estados_pagos_error']);
$baseUrl = htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8');
$tituloCabecera = 'Estados de pagos';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/../../includes/favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/estilos.css">
    <title>Estados de pagos | Hotel Premium</title>
</head>
<body class="panel">
    <div class="panel-panel">
        <?php require __DIR__ . '/../../includes/panel_cabecera.php'; ?>
        <div class="panel-distribucion">
            <?php require __DIR__ . '/../../includes/sidebar.php'; ?>
            <main class="panel-contenido pagina-habitaciones">
                <header class="habitaciones-cabecera">
                    <p>Consulta y administra las descripciones de los estados de pago.</p>
                </header>

                <?php if ($successMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-exito"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if ($errorMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <?php if ($paymentStates === []): ?>
                    <p class="mensaje-info">No hay estados de pago registrados.</p>
                <?php else: ?>
                    <div class="tabla-contenedor">
                        <table class="tabla-registros">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Descripción</th>
                                    <th>Pagos asociados</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($paymentStates as $paymentState): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($paymentState['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($paymentState['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= (int) $paymentState['pagos_asociados'] ?></td>
                                        <td>
                                            <a
                                                class="boton-secundario"
                                                href="<?= $baseUrl ?>/assets/modules/estados_pagos/editar.php?id=<?= (int) $paymentState['estado_pago_id'] ?>"
                                            >Editar</a>
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