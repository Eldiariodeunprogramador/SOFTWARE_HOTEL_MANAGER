<?php
$menuModuleLabels = [
    'reservas' => 'Reservas',
    'habitaciones' => 'Habitaciones',
    'huespedes' => 'Huéspedes',
    'pagos' => 'Pagos',
    'reportes' => 'Reportes',
];
$menuModuleIcons = [
    'reservas' => 'fa-calendar-check',
    'habitaciones' => 'fa-bed',
    'huespedes' => 'fa-users',
    'pagos' => 'fa-credit-card',
    'reportes' => 'fa-chart-column',
];
$menuModules = array_values(array_intersect(array_keys($menuModuleLabels), user_modules($pdo)));
$menuIsAdmin = user_has_role($pdo, 'Administrador');
$menuCanViewRoles = user_has_permission($pdo, 'roles.ver');
$menuBaseUrl = htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8');
?>
<aside class="panel-menu-lateral">
    <h2>Menú</h2>
    <nav aria-label="Navegación principal">
        <ul>
            <li>
                <a class="enlace-menu" href="<?= $menuBaseUrl ?>/assets/modules/dashboard/index.php">
                    <i class="fa-solid fa-house" aria-hidden="true"></i>
                    <span>Panel principal</span>
                </a>
            </li>
            <?php foreach ($menuModules as $module): ?>
                <li>
                    <a class="enlace-menu" href="<?= $menuBaseUrl ?>/assets/modules/<?= rawurlencode($module) ?>/index.php">
                        <i class="fa-solid <?= htmlspecialchars($menuModuleIcons[$module], ENT_QUOTES, 'UTF-8') ?>" aria-hidden="true"></i>
                        <span><?= htmlspecialchars($menuModuleLabels[$module], ENT_QUOTES, 'UTF-8') ?></span>
                    </a>
                </li>
            <?php endforeach; ?>
            <?php if ($menuIsAdmin): ?>
                <li>
                    <a class="enlace-menu" href="<?= $menuBaseUrl ?>/assets/modules/usuarios/index.php">
                        <i class="fa-solid fa-user-gear" aria-hidden="true"></i>
                        <span>Usuarios</span>
                    </a>
                </li>
                <li>
                    <a class="enlace-menu" href="<?= $menuBaseUrl ?>/assets/modules/personas/index.php">
                        <i class="fa-solid fa-address-card" aria-hidden="true"></i>
                        <span>Personas</span>
                    </a>
                </li>
            <?php endif; ?>
            <?php if ($menuCanViewRoles): ?>
                <li>
                    <a class="enlace-menu" href="<?= $menuBaseUrl ?>/assets/modules/roles/index.php">
                        <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
                        <span>Roles</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
</aside>
