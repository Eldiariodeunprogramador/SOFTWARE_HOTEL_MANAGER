<?php
$menuModuleLabels = [
    'huespedes' => 'Huéspedes',
];
$menuModuleIcons = [
    'huespedes' => 'fa-users',
];
$menuModules = array_values(array_intersect(array_keys($menuModuleLabels), user_modules($pdo)));
$menuCanViewReservations = user_has_permission($pdo, 'reservas.ver');
$menuCanViewPayments = user_has_permission($pdo, 'pagos.ver');
$menuCanRegisterPayments = user_has_permission($pdo, 'pagos.registrar');
$menuIsAdmin = user_has_role($pdo, 'Administrador');
$menuIsEmployee = user_has_role($pdo, 'Empleado');
$menuIsSupervisor = user_has_role($pdo, 'Supervisor');
$menuCanViewReports = $menuIsAdmin || $menuIsSupervisor || $menuIsEmployee;
$menuIsCustomer = user_has_role($pdo, 'Cliente') && !$menuIsAdmin && !$menuIsEmployee;
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
            <?php if ($menuCanViewReservations || $menuIsCustomer): ?>
                <li>
                    <a class="enlace-menu" href="<?= $menuBaseUrl ?>/assets/modules/reservas/<?= $menuIsCustomer ? 'mis_reservas.php' : 'index.php' ?>">
                        <i class="fa-solid fa-calendar-check" aria-hidden="true"></i>
                        <span><?= $menuIsCustomer ? 'Mis reservas' : 'Reservas' ?></span>
                    </a>
                </li>
            <?php endif; ?>
            <?php if ($menuCanViewPayments): ?>
                <li>
                    <a class="enlace-menu" href="<?= $menuBaseUrl ?>/assets/modules/pagos/index.php">
                        <i class="fa-solid fa-credit-card" aria-hidden="true"></i>
                        <span>Pagos</span>
                    </a>
                </li>
            <?php endif; ?>
            <?php if ($menuCanRegisterPayments): ?>
                <li>
                    <a class="enlace-menu" href="<?= $menuBaseUrl ?>/assets/modules/pagos/pagar_reserva.php">
                        <i class="fa-solid fa-money-bill-transfer" aria-hidden="true"></i>
                        <span>Pagar reserva</span>
                    </a>
                </li>
            <?php endif; ?>
            <?php if ($menuCanViewReports): ?>
                <li>
                    <a class="enlace-menu" href="<?= $menuBaseUrl ?>/assets/modules/reportes/index.php">
                        <i class="fa-solid fa-chart-column" aria-hidden="true"></i>
                        <span>Reportes</span>
                    </a>
                </li>
            <?php endif; ?>
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
            <?php endif; ?>
            <?php if ($menuCanViewRoles): ?>
                <li>
                    <a class="enlace-menu" href="<?= $menuBaseUrl ?>/assets/modules/roles/index.php">
                        <i class="fa-solid fa-user-shield" aria-hidden="true"></i>
                        <span>Roles</span>
                    </a>
                </li>
            <?php endif; ?>
            <?php if ($menuIsAdmin): ?>
                <li>
                    <a class="enlace-menu" href="<?= $menuBaseUrl ?>/assets/modules/habitaciones/index.php">
                        <i class="fa-solid fa-bed" aria-hidden="true"></i>
                        <span>Habitaciones</span>
                    </a>
                </li>
                <li>
                    <a class="enlace-menu" href="<?= $menuBaseUrl ?>/assets/modules/habitaciones/tipo_habitaciones/index.php">
                        <i class="fa-solid fa-bed" aria-hidden="true"></i>
                        <span>Tipos de habitaciones</span>
                    </a>
                </li>
                <li>
                    <a class="enlace-menu" href="<?= $menuBaseUrl ?>/assets/modules/habitaciones/estados_habitaciones/index.php">
                        <i class="fa-solid fa-door-open" aria-hidden="true"></i>
                        <span>Estados de habitaciones</span>
                    </a>
                </li>
                <li>
                    <a class="enlace-menu" href="<?= $menuBaseUrl ?>/assets/modules/estados_reservas/index.php">
                        <i class="fa-solid fa-list-check" aria-hidden="true"></i>
                        <span>Estados de reservas</span>
                    </a>
                </li>
                <li>
                    <a class="enlace-menu" href="<?= $menuBaseUrl ?>/assets/modules/estados_pagos/index.php">
                        <i class="fa-solid fa-credit-card" aria-hidden="true"></i>
                        <span>Estados de pagos</span>
                    </a>
                </li>
                <li>
                    <a class="enlace-menu" href="<?= $menuBaseUrl ?>/assets/modules/personas/index.php">
                        <i class="fa-solid fa-address-card" aria-hidden="true"></i>
                        <span>Personas</span>
                    </a>
                </li>
                <li>
                    <a class="enlace-menu" href="<?= $menuBaseUrl ?>/assets/modules/empleados/index.php">
                        <i class="fa-solid fa-user-tie" aria-hidden="true"></i>
                        <span>Empleados</span>
                    </a>
                </li>
                <li>
                    <a class="enlace-menu" href="<?= $menuBaseUrl ?>/assets/modules/clientes/index.php">
                        <i class="fa-solid fa-user-tag" aria-hidden="true"></i>
                        <span>Clientes</span>
                    </a>
                </li>
                <li>
                    <a class="enlace-menu" href="<?= $menuBaseUrl ?>/assets/modules/tipos_documentos/index.php">
                        <i class="fa-solid fa-id-card" aria-hidden="true"></i>
                        <span>Tipos de documentos</span>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
    </nav>
</aside>
