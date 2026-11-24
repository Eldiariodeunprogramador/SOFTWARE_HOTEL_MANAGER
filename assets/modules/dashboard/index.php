<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';

$modules = user_modules($pdo);
$statistics = $pdo->query(
    'SELECT
        (SELECT COUNT(*) FROM clientes) AS total_clientes,
        (SELECT COUNT(*) FROM habitaciones) AS total_habitaciones,
        (SELECT COUNT(*) FROM empleados) AS total_empleados,
        (SELECT COALESCE(SUM(p.monto), 0)
         FROM pagos p
         INNER JOIN estados_pagos ep ON ep.estado_pago_id = p.estado_pago_id
         WHERE ep.nombre = \'Pagado\') AS total_ganado,
        (SELECT COUNT(*) FROM usuarios) AS total_usuarios,
        (SELECT COUNT(*) FROM estados_reservas) AS total_estados_reservas,
        (SELECT COUNT(*) FROM habitaciones WHERE piso = 1) AS total_habitaciones_piso_1,
        (SELECT COUNT(*) FROM habitaciones WHERE piso = 2) AS total_habitaciones_piso_2,
        (SELECT COUNT(*) FROM habitaciones WHERE piso = 3) AS total_habitaciones_piso_3,
        (SELECT COUNT(*)
         FROM habitaciones h
         INNER JOIN estados_habitaciones eh ON eh.estado_habitacion_id = h.estado_habitacion_id
         WHERE eh.nombre = \'Disponible\') AS habitaciones_disponibles,
        (SELECT COUNT(*)
         FROM habitaciones h
         INNER JOIN estados_habitaciones eh ON eh.estado_habitacion_id = h.estado_habitacion_id
         WHERE eh.nombre = \'Limpieza\') AS habitaciones_limpieza,
        (SELECT COUNT(*)
         FROM habitaciones h
         INNER JOIN estados_habitaciones eh ON eh.estado_habitacion_id = h.estado_habitacion_id
         WHERE eh.nombre = \'Mantenimiento\') AS habitaciones_mantenimiento,
        (SELECT COUNT(*)
         FROM habitaciones h
         INNER JOIN estados_habitaciones eh ON eh.estado_habitacion_id = h.estado_habitacion_id
         WHERE eh.nombre = \'Ocupada\') AS habitaciones_ocupadas'
)->fetch();
$isCustomer = user_has_role($pdo, 'Cliente')
    && !user_has_role($pdo, 'Administrador')
    && !user_has_role($pdo, 'Empleado');
$moduleLabels = [
    'reservas' => 'Reservas',
    'huespedes' => 'Huéspedes',
    'pagos' => 'Pagos',
    'reportes' => 'Reportes',
];
$moduleIcons = [
    'reservas' => 'fa-calendar-check',
    'huespedes' => 'fa-users',
    'pagos' => 'fa-credit-card',
    'reportes' => 'fa-chart-column',
];
$tituloCabecera = 'Panel de Hotel Premium';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/../../includes/favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/css/estilos.css">
    <title>Panel | Hotel Premium</title>
</head>
<body class="panel">
    <div class="panel-panel">
        <?php require __DIR__ . '/../../includes/panel_cabecera.php'; ?>
        <div class="panel-distribucion">
            <?php require __DIR__ . '/../../includes/sidebar.php'; ?>
            <main class="panel-contenido">
                <h2>Módulos disponibles para tu rol</h2>
                <section class="dashboard-resumen" aria-label="Resumen del hotel">
                    <article class="dashboard-indicador estado-habitacion-disponible">
                        <i class="fa-solid fa-bed" aria-hidden="true"></i>
                        <div>
                            <h3>Habitaciones disponibles</h3>
                            <p><?= number_format((int) $statistics['habitaciones_disponibles']) ?></p>
                        </div>
                    </article>
                    <article class="dashboard-indicador estado-habitacion-limpieza">
                        <i class="fa-solid fa-broom" aria-hidden="true"></i>
                        <div>
                            <h3>Habitaciones en limpieza</h3>
                            <p><?= number_format((int) $statistics['habitaciones_limpieza']) ?></p>
                        </div>
                    </article>
                    <article class="dashboard-indicador estado-habitacion-mantenimiento">
                        <i class="fa-solid fa-screwdriver-wrench" aria-hidden="true"></i>
                        <div>
                            <h3>Habitaciones en mantenimiento</h3>
                            <p><?= number_format((int) $statistics['habitaciones_mantenimiento']) ?></p>
                        </div>
                    </article>
                    <article class="dashboard-indicador estado-habitacion-ocupada">
                        <i class="fa-solid fa-user-lock" aria-hidden="true"></i>
                        <div>
                            <h3>Habitaciones ocupadas</h3>
                            <p><?= number_format((int) $statistics['habitaciones_ocupadas']) ?></p>
                        </div>
                    </article>
                    <article class="dashboard-indicador">
                        <i class="fa-solid fa-users" aria-hidden="true"></i>
                        <div>
                            <h3>Total clientes</h3>
                            <p><?= number_format((int) $statistics['total_clientes']) ?></p>
                        </div>
                    </article>
                    <article class="dashboard-indicador">
                        <i class="fa-solid fa-bed" aria-hidden="true"></i>
                        <div>
                            <h3>Total habitaciones</h3>
                            <p><?= number_format((int) $statistics['total_habitaciones']) ?></p>
                        </div>
                    </article>
                    <article class="dashboard-indicador">
                        <i class="fa-solid fa-user-tie" aria-hidden="true"></i>
                        <div>
                            <h3>Total empleados</h3>
                            <p><?= number_format((int) $statistics['total_empleados']) ?></p>
                        </div>
                    </article>
                    <article class="dashboard-indicador">
                        <i class="fa-solid fa-money-bill-wave" aria-hidden="true"></i>
                        <div>
                            <h3>Total ganado</h3>
                            <p><?= number_format((float) $statistics['total_ganado'], 2, ',', '.') ?></p>
                        </div>
                    </article>
                    <article class="dashboard-indicador">
                        <i class="fa-solid fa-user-gear" aria-hidden="true"></i>
                        <div>
                            <h3>Total usuarios</h3>
                            <p><?= number_format((int) $statistics['total_usuarios']) ?></p>
                        </div>
                    </article>
                    <article class="dashboard-indicador">
                        <i class="fa-solid fa-list-check" aria-hidden="true"></i>
                        <div>
                            <h3>Estados de reservas</h3>
                            <p><?= number_format((int) $statistics['total_estados_reservas']) ?></p>
                        </div>
                    </article>
                    <article class="dashboard-indicador">
                        <i class="fa-solid fa-building" aria-hidden="true"></i>
                        <div>
                            <h3>Total habitaciones piso 1</h3>
                            <p><?= number_format((int) $statistics['total_habitaciones_piso_1']) ?></p>
                        </div>
                    </article>
                    <article class="dashboard-indicador">
                        <i class="fa-solid fa-building" aria-hidden="true"></i>
                        <div>
                            <h3>Total habitaciones piso 2</h3>
                            <p><?= number_format((int) $statistics['total_habitaciones_piso_2']) ?></p>
                        </div>
                    </article>
                    <article class="dashboard-indicador">
                        <i class="fa-solid fa-building" aria-hidden="true"></i>
                        <div>
                            <h3>Total habitaciones piso 3</h3>
                            <p><?= number_format((int) $statistics['total_habitaciones_piso_3']) ?></p>
                        </div>
                    </article>
                </section>
                <?php $availableModules = array_values(array_intersect(array_keys($moduleLabels), $modules)); ?>
                <?php if ($isCustomer): ?>
                    <?php $availableModules = array_values(array_diff($availableModules, ['reservas'])); ?>
                <?php endif; ?>
                <?php if ($availableModules === [] && !$isCustomer): ?>
                    <p class="mensaje-info">Tu rol no tiene permisos asignados a módulos. Solicita acceso al administrador.</p>
                <?php else: ?>
                    <nav class="modulos" aria-label="Módulos disponibles">
                        <?php if ($isCustomer): ?>
                            <a class="modulo" href="<?= htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/modules/reservas/mis_reservas.php">
                                <i class="fa-solid fa-calendar-check" aria-hidden="true"></i>
                                <span>Mis reservas</span>
                            </a>
                        <?php endif; ?>
                        <?php foreach ($availableModules as $module): ?>
                            <a
                                class="modulo"
                                href="<?= htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/modules/<?= rawurlencode($module) ?>/index.php"
                            >
                                <i class="fa-solid <?= htmlspecialchars($moduleIcons[$module], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                                <span><?= htmlspecialchars($moduleLabels[$module], ENT_QUOTES, 'UTF-8') ?></span>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>
