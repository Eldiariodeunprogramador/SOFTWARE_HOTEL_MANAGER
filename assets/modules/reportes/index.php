<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';

if (!user_has_role($pdo, 'Administrador')
    && !user_has_role($pdo, 'Supervisor')
    && !user_has_role($pdo, 'Empleado')
) {
    http_response_code(403);
    echo '<!DOCTYPE html><html lang="es"><meta charset="UTF-8"><title>Acceso denegado</title>'
        . '<body><h1>Acceso denegado</h1><p>Solo los administradores, supervisores y empleados pueden consultar reportes.</p>'
        . '<a href="' . htmlspecialchars(HOTEL_BASE_URL . '/assets/modules/dashboard/index.php', ENT_QUOTES, 'UTF-8')
        . '">Volver al panel</a></body></html>';
    exit;
}

$baseUrl = htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8');
$tituloCabecera = 'Reportes';

function report_date(string $value, string $fallback): string
{
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    $errors = DateTimeImmutable::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        return $fallback;
    }

    return $date->format('Y-m-d');
}

function report_csv_value($value): string
{
    $text = (string) ($value ?? '');
    if ($text !== '' && in_array($text[0], ['=', '+', '-', '@'], true)) {
        $text = "'" . $text;
    }

    return $text;
}

$today = (new DateTimeImmutable('today'))->format('Y-m-d');
$defaultStart = (new DateTimeImmutable('today'))->modify('-29 days')->format('Y-m-d');
$startDate = report_date($_GET['desde'] ?? '', $defaultStart);
$endDate = report_date($_GET['hasta'] ?? '', $today);
if ($startDate > $endDate) {
    [$startDate, $endDate] = [$endDate, $startDate];
}

$report = $_GET['tipo'] ?? 'reservas';
if (!in_array($report, ['reservas', 'pagos', 'ocupacion'], true)) {
    $report = 'reservas';
}

$reservationSummary = $pdo->prepare(
    'SELECT er.nombre AS estado, COUNT(*) AS cantidad,
            COALESCE(SUM(r.precio_noche * GREATEST(DATEDIFF(r.fecha_salida, r.fecha_entrada), 0)), 0) AS total
     FROM reservas r
     INNER JOIN estados_reservas er ON er.estado_reserva_id = r.estado_reserva_id
     WHERE DATE(r.fecha_reserva) BETWEEN :desde AND :hasta
     GROUP BY er.nombre
     ORDER BY er.nombre'
);
$reservationSummary->execute(['desde' => $startDate, 'hasta' => $endDate]);
$reservationByStatus = $reservationSummary->fetchAll();

$paymentSummary = $pdo->prepare(
    'SELECT ep.nombre AS estado, COUNT(*) AS cantidad, COALESCE(SUM(p.monto), 0) AS total
     FROM pagos p
     INNER JOIN estados_pagos ep ON ep.estado_pago_id = p.estado_pago_id
     WHERE DATE(p.fecha_pago) BETWEEN :desde AND :hasta
     GROUP BY ep.nombre
     ORDER BY ep.nombre'
);
$paymentSummary->execute(['desde' => $startDate, 'hasta' => $endDate]);
$paymentByStatus = $paymentSummary->fetchAll();

$roomStatus = $pdo->query(
    'SELECT eh.nombre AS estado, COUNT(*) AS cantidad
     FROM habitaciones h
     INNER JOIN estados_habitaciones eh ON eh.estado_habitacion_id = h.estado_habitacion_id
     GROUP BY eh.nombre
     ORDER BY eh.nombre'
)->fetchAll();

$totals = [
    'reservas' => 0,
    'pagos_pagado' => 0,
];
$reservationCount = $pdo->prepare(
    'SELECT COUNT(*) FROM reservas
     WHERE DATE(fecha_reserva) BETWEEN :desde AND :hasta'
);
$reservationCount->execute(['desde' => $startDate, 'hasta' => $endDate]);
$totals['reservas'] = (int) $reservationCount->fetchColumn();
foreach ($paymentByStatus as $row) {
    if ($row['estado'] === 'Pagado') {
        $totals['pagos_pagado'] = (float) $row['total'];
    }
}

if ($report === 'reservas') {
    $statement = $pdo->prepare(
        'SELECT r.reserva_id, r.fecha_reserva, r.fecha_entrada, r.fecha_salida,
                r.numero_huespedes, r.precio_noche,
                CONCAT(pc.nombres, \' \', pc.apellidos) AS cliente,
                h.numero AS habitacion, th.nombre AS tipo_habitacion,
                er.nombre AS estado
         FROM reservas r
         INNER JOIN clientes c ON c.cliente_id = r.cliente_id
         INNER JOIN personas pc ON pc.persona_id = c.persona_id
         INNER JOIN habitaciones h ON h.habitacion_id = r.habitacion_id
         INNER JOIN tipos_habitaciones th ON th.tipo_habitacion_id = h.tipo_habitacion_id
         INNER JOIN estados_reservas er ON er.estado_reserva_id = r.estado_reserva_id
         WHERE DATE(r.fecha_reserva) BETWEEN :desde AND :hasta
         ORDER BY r.fecha_reserva DESC, r.reserva_id DESC'
    );
    $statement->execute(['desde' => $startDate, 'hasta' => $endDate]);
    $rows = $statement->fetchAll();
    $columns = [
        'Reserva', 'Fecha registro', 'Entrada', 'Salida', 'Cliente',
        'Habitación', 'Tipo', 'Huéspedes', 'Precio/noche', 'Estado',
    ];
    $csvRows = array_map(static fn(array $row): array => [
        $row['reserva_id'],
        $row['fecha_reserva'],
        $row['fecha_entrada'],
        $row['fecha_salida'],
        $row['cliente'],
        $row['habitacion'],
        $row['tipo_habitacion'],
        $row['numero_huespedes'],
        $row['precio_noche'],
        $row['estado'],
    ], $rows);
} elseif ($report === 'pagos') {
    $statement = $pdo->prepare(
        'SELECT p.pago_id, p.fecha_pago, p.monto, p.referencia, p.observaciones,
                r.reserva_id,
                CONCAT(pc.nombres, \' \', pc.apellidos) AS cliente,
                mp.nombre AS metodo_pago, ep.nombre AS estado_pago
         FROM pagos p
         INNER JOIN reservas r ON r.reserva_id = p.reserva_id
         INNER JOIN clientes c ON c.cliente_id = r.cliente_id
         INNER JOIN personas pc ON pc.persona_id = c.persona_id
         INNER JOIN metodos_pagos mp ON mp.metodo_pago_id = p.metodo_pago_id
         INNER JOIN estados_pagos ep ON ep.estado_pago_id = p.estado_pago_id
         WHERE DATE(p.fecha_pago) BETWEEN :desde AND :hasta
         ORDER BY p.fecha_pago DESC, p.pago_id DESC'
    );
    $statement->execute(['desde' => $startDate, 'hasta' => $endDate]);
    $rows = $statement->fetchAll();
    $columns = ['Pago', 'Fecha', 'Reserva', 'Cliente', 'Método', 'Monto', 'Referencia', 'Estado', 'Observaciones'];
    $csvRows = array_map(static fn(array $row): array => [
        $row['pago_id'],
        $row['fecha_pago'],
        $row['reserva_id'],
        $row['cliente'],
        $row['metodo_pago'],
        $row['monto'],
        $row['referencia'],
        $row['estado_pago'],
        $row['observaciones'],
    ], $rows);
} else {
    $rows = $pdo->query(
        'SELECT h.habitacion_id, h.numero, h.piso, th.nombre AS tipo_habitacion,
                eh.nombre AS estado, h.precio_noche
         FROM habitaciones h
         INNER JOIN tipos_habitaciones th ON th.tipo_habitacion_id = h.tipo_habitacion_id
         INNER JOIN estados_habitaciones eh ON eh.estado_habitacion_id = h.estado_habitacion_id
         ORDER BY h.piso, h.numero'
    )->fetchAll();
    $columns = ['Habitación', 'Piso', 'Tipo', 'Estado', 'Precio/noche'];
    $csvRows = array_map(static fn(array $row): array => [
        $row['numero'],
        $row['piso'],
        $row['tipo_habitacion'],
        $row['estado'],
        $row['precio_noche'],
    ], $rows);
}

if (($_GET['export'] ?? '') === 'csv') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="reporte-' . $report . '-' . $startDate . '-a-' . $endDate . '.csv"');
    echo "\xEF\xBB\xBF";
    $output = fopen('php://output', 'w');
    fputcsv($output, $columns);
    foreach ($csvRows as $csvRow) {
        fputcsv($output, array_map('report_csv_value', $csvRow));
    }
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/../../includes/favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/estilos.css">
    <title>Reportes | Hotel Premium</title>
</head>
<body class="panel">
    <div class="panel-panel">
        <?php require __DIR__ . '/../../includes/panel_cabecera.php'; ?>
        <div class="panel-distribucion">
            <?php require __DIR__ . '/../../includes/sidebar.php'; ?>
            <main class="panel-contenido pagina-reportes">
                <header class="habitaciones-cabecera">
                    <p>Obtén información de reservas, pagos y estado actual de las habitaciones.</p>
                </header>

                <form class="filtros-reportes" method="get">
                    <div>
                        <label for="tipo">Tipo de reporte</label>
                        <select id="tipo" name="tipo">
                            <option value="reservas" <?= $report === 'reservas' ? 'selected' : '' ?>>Reservas</option>
                            <option value="pagos" <?= $report === 'pagos' ? 'selected' : '' ?>>Pagos</option>
                            <option value="ocupacion" <?= $report === 'ocupacion' ? 'selected' : '' ?>>Ocupación de habitaciones</option>
                        </select>
                    </div>
                    <div>
                        <label for="desde">Desde</label>
                        <input id="desde" name="desde" type="date" value="<?= htmlspecialchars($startDate, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div>
                        <label for="hasta">Hasta</label>
                        <input id="hasta" name="hasta" type="date" value="<?= htmlspecialchars($endDate, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <button class="boton-accion" type="submit">
                        <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                        Consultar
                    </button>
                    <a class="boton-secundario" href="?tipo=<?= rawurlencode($report) ?>&amp;desde=<?= rawurlencode($startDate) ?>&amp;hasta=<?= rawurlencode($endDate) ?>&amp;export=csv">
                        <i class="fa-solid fa-file-csv" aria-hidden="true"></i>
                        Exportar CSV
                    </a>
                </form>

                <section class="dashboard-resumen" aria-label="Resumen de reportes">
                    <article class="dashboard-indicador">
                        <i class="fa-solid fa-calendar-check" aria-hidden="true"></i>
                        <div>
                            <h3>Reservas en el período</h3>
                            <p><?= number_format($totals['reservas']) ?></p>
                        </div>
                    </article>
                    <article class="dashboard-indicador">
                        <i class="fa-solid fa-money-bill-wave" aria-hidden="true"></i>
                        <div>
                            <h3>Pagos confirmados</h3>
                            <p><?= number_format($totals['pagos_pagado'], 2, ',', '.') ?></p>
                        </div>
                    </article>
                    <article class="dashboard-indicador">
                        <i class="fa-solid fa-door-open" aria-hidden="true"></i>
                        <div>
                            <h3>Habitaciones disponibles</h3>
                            <p><?php
                                $available = 0;
                                foreach ($roomStatus as $status) {
                                    if ($status['estado'] === 'Disponible') {
                                        $available = (int) $status['cantidad'];
                                    }
                                }
                                echo number_format($available);
                            ?></p>
                        </div>
                    </article>
                </section>

                <section class="resumen-estados" aria-label="Resumen por estados">
                    <div>
                        <h2>Reservas por estado</h2>
                        <?php if ($reservationByStatus === []): ?>
                            <p class="mensaje-info">No hay reservas en el período seleccionado.</p>
                        <?php else: ?>
                            <ul>
                                <?php foreach ($reservationByStatus as $row): ?>
                                    <li><span><?= htmlspecialchars($row['estado'], ENT_QUOTES, 'UTF-8') ?></span><strong><?= (int) $row['cantidad'] ?></strong></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                    <div>
                        <h2>Pagos por estado</h2>
                        <?php if ($paymentByStatus === []): ?>
                            <p class="mensaje-info">No hay pagos en el período seleccionado.</p>
                        <?php else: ?>
                            <ul>
                                <?php foreach ($paymentByStatus as $row): ?>
                                    <li><span><?= htmlspecialchars($row['estado'], ENT_QUOTES, 'UTF-8') ?></span><strong><?= number_format((float) $row['total'], 2, ',', '.') ?></strong></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="detalle-reporte">
                    <h2>
                        <?= $report === 'reservas' ? 'Detalle de reservas' : ($report === 'pagos' ? 'Detalle de pagos' : 'Estado de habitaciones') ?>
                    </h2>
                    <?php if ($rows === []): ?>
                        <p class="mensaje-info">No hay información para los filtros seleccionados.</p>
                    <?php else: ?>
                        <div class="tabla-contenedor">
                            <table class="tabla-registros">
                                <thead>
                                    <tr>
                                        <?php foreach ($columns as $column): ?>
                                            <th><?= htmlspecialchars($column, ENT_QUOTES, 'UTF-8') ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($rows as $row): ?>
                                        <?php if ($report === 'reservas'): ?>
                                            <tr>
                                                <td>#<?= (int) $row['reserva_id'] ?></td>
                                                <td><?= htmlspecialchars($row['fecha_reserva'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($row['fecha_entrada'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($row['fecha_salida'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($row['cliente'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($row['habitacion'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($row['tipo_habitacion'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= (int) $row['numero_huespedes'] ?></td>
                                                <td><?= number_format((float) $row['precio_noche'], 2, ',', '.') ?></td>
                                                <td><?= htmlspecialchars($row['estado'], ENT_QUOTES, 'UTF-8') ?></td>
                                            </tr>
                                        <?php elseif ($report === 'pagos'): ?>
                                            <tr>
                                                <td>#<?= (int) $row['pago_id'] ?></td>
                                                <td><?= htmlspecialchars($row['fecha_pago'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td>#<?= (int) $row['reserva_id'] ?></td>
                                                <td><?= htmlspecialchars($row['cliente'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($row['metodo_pago'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= number_format((float) $row['monto'], 2, ',', '.') ?></td>
                                                <td><?= htmlspecialchars($row['referencia'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($row['estado_pago'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($row['observaciones'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                            </tr>
                                        <?php else: ?>
                                            <tr>
                                                <td><?= htmlspecialchars($row['numero'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= (int) $row['piso'] ?></td>
                                                <td><?= htmlspecialchars($row['tipo_habitacion'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($row['estado'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= number_format((float) $row['precio_noche'], 2, ',', '.') ?></td>
                                            </tr>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </main>
        </div>
    </div>
</body>
</html>