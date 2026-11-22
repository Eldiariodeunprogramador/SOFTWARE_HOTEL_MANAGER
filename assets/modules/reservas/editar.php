<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('reservas.editar');
require_once __DIR__ . '/../../config/database.php';

$reservationIdInput = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['reserva_id'] ?? '')
    : ($_GET['id'] ?? '');
$reservationId = is_string($reservationIdInput)
    ? filter_var($reservationIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
if ($reservationId === false) {
    http_response_code(400);
    exit('ID de reserva inválido.');
}

$reservationStatement = $pdo->prepare(
    'SELECT r.reserva_id, r.cliente_id, r.habitacion_id, r.empleado_id,
            r.fecha_entrada, r.fecha_salida, r.numero_huespedes,
            r.precio_noche, r.observaciones, er.nombre AS estado
     FROM reservas r
     INNER JOIN estados_reservas er ON er.estado_reserva_id = r.estado_reserva_id
     WHERE r.reserva_id = :reserva_id
     LIMIT 1'
);
$reservationStatement->execute(['reserva_id' => $reservationId]);
$reservationRecord = $reservationStatement->fetch();
if (!$reservationRecord) {
    http_response_code(404);
    exit('No se encontró la reserva solicitada.');
}
if (in_array($reservationRecord['estado'], ['Cancelada', 'Finalizada'], true)) {
    http_response_code(409);
    exit('No se puede editar una reserva cancelada o finalizada.');
}

$clients = $pdo->query(
    'SELECT c.cliente_id, p.nombres, p.apellidos
     FROM clientes c
     INNER JOIN personas p ON p.persona_id = c.persona_id
     ORDER BY p.apellidos, p.nombres'
)->fetchAll();
$roomsStatement = $pdo->prepare(
    'SELECT h.habitacion_id, h.numero, th.capacidad, eh.nombre AS estado
     FROM habitaciones h
     INNER JOIN tipos_habitaciones th ON th.tipo_habitacion_id = h.tipo_habitacion_id
     INNER JOIN estados_habitaciones eh ON eh.estado_habitacion_id = h.estado_habitacion_id
     WHERE eh.nombre NOT IN (\'Mantenimiento\', \'Limpieza\')
        OR h.habitacion_id = :habitacion_actual
     ORDER BY h.numero'
);
$roomsStatement->execute(['habitacion_actual' => $reservationRecord['habitacion_id']]);
$rooms = $roomsStatement->fetchAll();
$employeesStatement = $pdo->prepare(
    'SELECT e.empleado_id, p.nombres, p.apellidos
     FROM empleados e
     INNER JOIN personas p ON p.persona_id = e.persona_id
     WHERE e.activo = 1 OR e.empleado_id = :empleado_actual
     ORDER BY p.apellidos, p.nombres'
);
$employeesStatement->execute([
    'empleado_actual' => $reservationRecord['empleado_id'] ?? 0,
]);
$employees = $employeesStatement->fetchAll();
$reservation = [
    'cliente_id' => (string) $reservationRecord['cliente_id'],
    'habitacion_id' => (string) $reservationRecord['habitacion_id'],
    'empleado_id' => $reservationRecord['empleado_id'] === null ? '' : (string) $reservationRecord['empleado_id'],
    'fecha_entrada' => $reservationRecord['fecha_entrada'],
    'fecha_salida' => $reservationRecord['fecha_salida'],
    'numero_huespedes' => (string) $reservationRecord['numero_huespedes'],
    'observaciones' => $reservationRecord['observaciones'] ?? '',
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
        || $guestCount === false || !$validDates || strlen($reservation['observaciones']) > 255) {
        $errorMessage = 'Verifica el cliente, la habitación, el empleado, las fechas, el número de huéspedes y las observaciones.';
    } else {
        try {
            $pdo->beginTransaction();
            $lockedReservationStatement = $pdo->prepare(
                'SELECT r.habitacion_id, r.estado_reserva_id, r.precio_noche, er.nombre AS estado
                 FROM reservas r
                 INNER JOIN estados_reservas er ON er.estado_reserva_id = r.estado_reserva_id
                 WHERE r.reserva_id = :reserva_id
                 FOR UPDATE'
            );
            $lockedReservationStatement->execute(['reserva_id' => $reservationId]);
            $lockedReservation = $lockedReservationStatement->fetch();
            if (!$lockedReservation || in_array($lockedReservation['estado'], ['Cancelada', 'Finalizada'], true)) {
                $pdo->rollBack();
                http_response_code(409);
                exit('La reserva ya no se puede editar.');
            }

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
            $sameRoom = $room && (int) $room['habitacion_id'] === (int) $lockedReservation['habitacion_id'];
            if (!$room || in_array($room['estado'], ['Mantenimiento', 'Limpieza'], true)
                || (int) $guestCount > (int) ($room['capacidad'] ?? 0)
                || ($reservation['fecha_entrada'] <= date('Y-m-d')
                    && $room['estado'] !== 'Disponible' && !$sameRoom)) {
                $pdo->rollBack();
                $errorMessage = 'La habitación no está disponible para esas fechas o no tiene capacidad suficiente.';
            } else {
                $overlapStatement = $pdo->prepare(
                    'SELECT COUNT(*)
                     FROM reservas r
                     INNER JOIN estados_reservas er ON er.estado_reserva_id = r.estado_reserva_id
                     WHERE r.habitacion_id = :habitacion_id
                       AND r.reserva_id <> :reserva_id
                       AND er.nombre <> :estado_cancelado
                       AND r.fecha_entrada < :fecha_salida
                       AND r.fecha_salida > :fecha_entrada'
                );
                $overlapStatement->execute([
                    'habitacion_id' => $roomId,
                    'reserva_id' => $reservationId,
                    'estado_cancelado' => 'Cancelada',
                    'fecha_salida' => $reservation['fecha_salida'],
                    'fecha_entrada' => $reservation['fecha_entrada'],
                ]);
                if ((int) $overlapStatement->fetchColumn() > 0) {
                    $pdo->rollBack();
                    $errorMessage = 'La habitación ya tiene una reserva para esas fechas.';
                } else {
                    $nightlyRate = $sameRoom
                        ? $lockedReservation['precio_noche']
                        : $room['precio_noche'];
                    $updateStatement = $pdo->prepare(
                        'UPDATE reservas
                         SET cliente_id = :cliente_id, habitacion_id = :habitacion_id,
                             empleado_id = :empleado_id, fecha_entrada = :fecha_entrada,
                             fecha_salida = :fecha_salida, numero_huespedes = :numero_huespedes,
                             precio_noche = :precio_noche, observaciones = :observaciones
                         WHERE reserva_id = :reserva_id'
                    );
                    $updateStatement->execute([
                        'cliente_id' => $clientId,
                        'habitacion_id' => $roomId,
                        'empleado_id' => $employeeId,
                        'fecha_entrada' => $reservation['fecha_entrada'],
                        'fecha_salida' => $reservation['fecha_salida'],
                        'numero_huespedes' => $guestCount,
                        'precio_noche' => $nightlyRate,
                        'observaciones' => $reservation['observaciones'] !== '' ? $reservation['observaciones'] : null,
                        'reserva_id' => $reservationId,
                    ]);
                    $pdo->commit();
                    $_SESSION['reservas_mensaje'] = 'Los cambios de la reserva se guardaron correctamente.';
                    redirect_to('/assets/modules/reservas/index.php');
                }
            }
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Hotel Manager reservation update failed: ' . $exception->getMessage());
            $errorMessage = 'No se pudieron guardar los cambios. Verifica los datos relacionados.';
        }
    }
}

$baseUrl = htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8');
$tituloCabecera = 'Editar reserva';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/../../includes/favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/estilos.css">
    <title>Editar reserva | Hotel Premium</title>
</head>
<body class="panel">
    <div class="panel-panel">
        <?php require __DIR__ . '/../../includes/panel_cabecera.php'; ?>
        <div class="panel-distribucion">
            <?php require __DIR__ . '/../../includes/sidebar.php'; ?>
            <main class="panel-contenido pagina-habitaciones">
                <header class="habitaciones-cabecera">
                    <p>Actualiza los datos de la reserva.</p>
                    <a class="boton-secundario" href="<?= $baseUrl ?>/assets/modules/reservas/index.php">Volver a reservas</a>
                </header>

                <?php if ($errorMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <form class="formulario-habitacion" method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="reserva_id" value="<?= (int) $reservationId ?>">

                    <label for="cliente_id">Cliente</label>
                    <select id="cliente_id" name="cliente_id" required>
                        <?php foreach ($clients as $client): ?>
                            <option value="<?= (int) $client['cliente_id'] ?>" <?= (string) $client['cliente_id'] === $reservation['cliente_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($client['nombres'] . ' ' . $client['apellidos'], ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label for="habitacion_id">Habitación</label>
                    <select id="habitacion_id" name="habitacion_id" required>
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

                    <p class="ayuda-formulario">Estado actual: <?= htmlspecialchars($reservationRecord['estado'], ENT_QUOTES, 'UTF-8') ?>. Para cancelar, utiliza la acción correspondiente en el listado.</p>
                    <button class="boton-accion" type="submit">Guardar cambios</button>
                </form>
            </main>
        </div>
    </div>
</body>
</html>