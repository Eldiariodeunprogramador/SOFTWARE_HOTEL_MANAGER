<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';

$modules = user_modules($pdo);
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
                <?php $availableModules = array_values(array_intersect(array_keys($moduleLabels), $modules)); ?>
                <?php if ($availableModules === []): ?>
                    <p class="mensaje-info">Tu rol no tiene permisos asignados a módulos. Solicita acceso al administrador.</p>
                <?php else: ?>
                    <nav class="modulos" aria-label="Módulos disponibles">
                        <?php foreach ($availableModules as $module): ?>
                            <a class="modulo" href="<?= htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/modules/<?= rawurlencode($module) ?>/index.php">
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
