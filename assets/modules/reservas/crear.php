<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('reservas.crear');
require_once __DIR__ . '/../../config/database.php';

$canManageReservations = user_has_role($pdo, 'Administrador') || user_has_role($pdo, 'Empleado');
if (!$canManageReservations) {
    if (user_has_role($pdo, 'Cliente')) {
        redirect_to('/assets/modules/reservas/mis_reservas.php');
    }

    http_response_code(403);
    exit('Tu rol no puede crear reservas para otros clientes.');
}

$clients = $pdo->query(
    'SELECT c.cliente_id, p.nombres, p.apellidos
     FROM clientes c
     INNER JOIN personas p ON p.persona_id = c.persona_id
     ORDER BY p.apellidos, p.nombres'
)->fetchAll();
$rooms = $pdo->query(
    'SELECT h.habitacion_id, h.numero, th.capacidad, eh.nombre AS estado
     FROM habitaciones h
     INNER JOIN tipos_habitaciones th ON th.tipo_habitacion_id = h.tipo_habitacion_id
     INNER JOIN estados_habitaciones eh ON eh.estado_habitacion_id = h.estado_habitacion_id
     WHERE eh.nombre NOT IN (\'Mantenimiento\', \'Limpieza\')
     ORDER BY h.numero'
)->fetchAll();
$employees = $pdo->query(
    'SELECT e.empleado_id, p.nombres, p.apellidos
     FROM empleados e
     INNER JOIN personas p ON p.persona_id = e.persona_id
     WHERE e.activo = 1
     ORDER BY p.apellidos, p.nombres'
)->fetchAll();
$pendingStateStatement = $pdo->prepare(
    'SELECT estado_reserva_id FROM estados_reservas WHERE nombre = :nombre AND activo = 1 LIMIT 1'
);
$pendingStateStatement->execute(['nombre' => 'Pendiente']);
$pendingStateId = $pendingStateStatement->fetchColumn();
$reservation = [
    'cliente_id' => '',
    'habitacion_id' => '',
    'empleado_id' => '',
    'fecha_entrada' => '',
    'fecha_salida' => '',
    'numero_huespedes' => '',
    'observaciones' => '',
];
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Solicitud inválida. Vuelve a cargar el formulario e inténtalo de nuevo.');
    }

    $validFieldTypes = true;
    foreach ($reservation as $field => $value) {
        $fieldValue = $_POST[$field] ?? '';
        if (!is_string($fieldValue)) {
            $validFieldTypes = false;
            $fieldValue = '';
        }
        $reservation[$field] = trim($fieldValue);
    }
    $clientId = filter_var($reservation['cliente_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $roomId = filter_var($reservation['habitacion_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $employeeId = $reservation['empleado_id'] === ''
        ? null
        : filter_var($reservation['empleado_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $guestCount = filter_var($reservation['numero_huespedes'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $checkIn = DateTimeImmutable::createFromFormat('!Y-m-d', $reservation['fecha_entrada']);
    $checkOut = DateTimeImmutable::createFromFormat('!Y-m-d', $reservation['fecha_salida']);
    $validDates = $checkIn instanceof DateTimeImmutable
        && $checkOut instanceof DateTimeImmutable
        && $checkIn->format('Y-m-d') === $reservation['fecha_entrada']
        && $checkOut->format('Y-m-d') === $reservation['fecha_salida']
        && $checkOut > $checkIn;
    $clientIds = array_map('intval', array_column($clients, 'cliente_id'));
    $employeeIds = array_map('intval', array_column($employees, 'empleado_id'));
    $roomIds = array_map('intval', array_column($rooms, 'habitacion_id'));

    if (!$validFieldTypes || $clientId === false || !in_array((int) $clientId, $clientIds, true)
        || $roomId === false || !in_array((int) $roomId, $roomIds, true)
        || ($employeeId !== null && ($employeeId === false || !in_array((int) $employeeId, $employeeIds, true)))
        || $guestCount === false || !$validDates || strlen($reservation['observaciones']) > 255
        || !$pendingStateId) {
        $errorMessage = 'Verifica el cliente, la habitación, el empleado, las fechas, el número de huéspedes y las observaciones.';
        if (!$pendingStateId) {
            $errorMessage = 'No existe el estado de reserva Pendiente. Verifica los estados configurados.';
        }
    } else {
        try {
            $pdo->beginTransaction();
            $roomStatement = $pdo->prepare(
                'SELECT h.habitacion_id, th.capacidad, th.precio_noche, eh.nombre AS estado
                 FROM habitaciones h
                 INNER JOIN tipos_habitaciones th ON th.tipo_habitacion_id = h.tipo_habitacion_id
                 INNER JOIN estados_habitaciones eh ON eh.estado_habitacion_id = h.estado_habitacion_id
                 WHERE h.habitacion_id = :habitacion_id
                 FOR UPDATE'
            );
            $roomStatement->execute(['habitacion_id' => $roomId]);
            $room = $roomStatement->fetch();
            if (!$room || in_array($room['estado'], ['Mantenimiento', 'Limpieza'], true)
                || (int) $guestCount > (int) ($room['capacidad'] ?? 0)
                || ($reservation['fecha_entrada'] <= date('Y-m-d') && $room['estado'] !== 'Disponible')) {
                $pdo->rollBack();
                $errorMessage = 'La habitación no está disponible para esas fechas o no tiene capacidad suficiente.';
            } else {
                $overlapStatement = $pdo->prepare(
                    'SELECT COUNT(*)
                     FROM reservas r
                     INNER JOIN estados_reservas er ON er.estado_reserva_id = r.estado_reserva_id
                     WHERE r.habitacion_id = :habitacion_id
                       AND er.nombre <> :estado_cancelado
                       AND r.fecha_entrada < :fecha_salida
                       AND r.fecha_salida > :fecha_entrada'
                );
                $overlapStatement->execute([
                    'habitacion_id' => $roomId,
                    'estado_cancelado' => 'Cancelada',
                    'fecha_salida' => $reservation['fecha_salida'],
                    'fecha_entrada' => $reservation['fecha_entrada'],
                ]);
                if ((int) $overlapStatement->fetchColumn() > 0) {
                    $pdo->rollBack();
                    $errorMessage = 'La habitación ya tiene una reserva para esas fechas.';
                } else {
                    $insertStatement = $pdo->prepare(
                        'INSERT INTO reservas
                            (cliente_id, habitacion_id, empleado_id, estado_reserva_id,
                             fecha_entrada, fecha_salida, numero_huespedes, precio_noche, observaciones)
                         VALUES
                            (:cliente_id, :habitacion_id, :empleado_id, :estado_reserva_id,
                             :fecha_entrada, :fecha_salida, :numero_huespedes, :precio_noche, :observaciones)'
                    );
                    $insertStatement->execute([
                        'cliente_id' => $clientId,
                        'habitacion_id' => $roomId,
                        'empleado_id' => $employeeId,
                        'estado_reserva_id' => $pendingStateId,
                        'fecha_entrada' => $reservation['fecha_entrada'],
                        'fecha_salida' => $reservation['fecha_salida'],
                        'numero_huespedes' => $guestCount,
                        'precio_noche' => $room['precio_noche'],
                        'observaciones' => $reservation['observaciones'] !== '' ? $reservation['observaciones'] : null,
                    ]);
                    $pdo->commit();
                    $_SESSION['reservas_mensaje'] = 'La reserva se creó correctamente.';
                    redirect_to('/assets/modules/reservas/index.php');
                }
            }
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Hotel Manager reservation creation failed: ' . $exception->getMessage());
            $errorMessage = 'No se pudo crear la reserva. Verifica que los datos relacionados sigan disponibles.';
        }
    }
}

$baseUrl = htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8');
$tituloCabecera = 'Crear reserva';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/../../includes/favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/estilos.css">
    <title>Crear reserva | Hotel Premium</title>
</head>
<body class="panel">
    <div class="panel-panel">
        <?php require __DIR__ . '/../../includes/panel_cabecera.php'; ?>
        <div class="panel-distribucion">
            <?php require __DIR__ . '/../../includes/sidebar.php'; ?>
            <main class="panel-contenido pagina-habitaciones">
                <header class="habitaciones-cabecera">
                    <p>Registra el cliente, habitación y fechas de la reserva.</p>
                    <a class="boton-secundario" href="<?= $baseUrl ?>/assets/modules/reservas/index.php">Volver a reservas</a>
                </header>

                <?php if ($errorMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if ($clients === []): ?>
                    <p class="mensaje-registro mensaje-error">No hay clientes registrados para crear una reserva.</p>
                <?php endif; ?>
                <?php if ($rooms === []): ?>
                    <p class="mensaje-registro mensaje-error">No hay habitaciones que puedan reservarse.</p>
                <?php endif; ?>
                <?php if ($employees === []): ?>
                    <p class="mensaje-info">No hay empleados activos. Puedes registrar la reserva sin asignar un empleado.</p>
                <?php endif; ?>
                <?php if (!$pendingStateId): ?>
                    <p class="mensaje-registro mensaje-error">No existe el estado de reserva Pendiente. Verifica los estados configurados.</p>
                <?php endif; ?>

                <form class="formulario-habitacion" method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                    <label for="cliente_id">Cliente</label>
                    <select id="cliente_id" name="cliente_id" required <?= $clients === [] ? 'disabled' : '' ?>>
                        <option value="">Selecciona un cliente</option>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= (int) $client['cliente_id'] ?>" <?= (string) $client['cliente_id'] === $reservation['cliente_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($client['nombres'] . ' ' . $client['apellidos'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="habitacion_id">Habitación</label>
                    <select id="habitacion_id" name="habitacion_id" required <?= $rooms === [] ? 'disabled' : '' ?>>
                        <option value="">Selecciona una habitación</option>
                        <?php foreach ($rooms as $roomOption): ?>
                            <option value="<?= (int) $roomOption['habitacion_id'] ?>" <?= (string) $roomOption['habitacion_id'] === $reservation['habitacion_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars('Habitación ' . $roomOption['numero'] . ' — capacidad ' . $roomOption['capacidad'] . ' — ' . $roomOption['estado'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="empleado_id">Empleado (opcional)</label>
                    <select id="empleado_id" name="empleado_id">
                        <option value="">Sin asignar</option>
                        <?php foreach ($employees as $employee): ?>
                            <option value="<?= (int) $employee['empleado_id'] ?>" <?= (string) $employee['empleado_id'] === $reservation['empleado_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($employee['nombres'] . ' ' . $employee['apellidos'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="fecha_entrada">Fecha de entrada</label>
                    <input id="fecha_entrada" name="fecha_entrada" type="date" required value="<?= htmlspecialchars($reservation['fecha_entrada'], ENT_QUOTES, 'UTF-8') ?>">

                    <label for="fecha_salida">Fecha de salida</label>
                    <input id="fecha_salida" name="fecha_salida" type="date" required value="<?= htmlspecialchars($reservation['fecha_salida'], ENT_QUOTES, 'UTF-8') ?>">

                    <label for="numero_huespedes">Número de huéspedes</label>
                    <input id="numero_huespedes" name="numero_huespedes" type="number" min="1" step="1" required value="<?= htmlspecialchars($reservation['numero_huespedes'], ENT_QUOTES, 'UTF-8') ?>">

                    <label for="observaciones">Observaciones (opcional)</label>
                    <textarea id="observaciones" name="observaciones" maxlength="255" rows="4"><?= htmlspecialchars($reservation['observaciones'], ENT_QUOTES, 'UTF-8') ?></textarea>

                    <button class="boton-accion" type="submit" <?= $clients === [] || $rooms === [] || !$pendingStateId ? 'disabled' : '' ?>>Crear reserva</button>
                </form>
            </main>
        </div>
    </div>
</body>
</html>