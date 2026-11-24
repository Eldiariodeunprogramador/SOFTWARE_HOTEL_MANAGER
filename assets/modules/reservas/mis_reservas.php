<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';
require_role($pdo, 'Cliente');

$clientStatement = $pdo->prepare(
    'SELECT c.cliente_id
     FROM clientes c
     INNER JOIN usuarios u ON u.persona_id = c.persona_id
     WHERE u.usuario_id = :usuario_id
     LIMIT 1'
);
$clientStatement->execute(['usuario_id' => $_SESSION['usuario_id']]);
$clientId = $clientStatement->fetchColumn();
if (!$clientId) {
    http_response_code(403);
    exit('La cuenta no está asociada a un perfil de cliente.');
}

$rooms = $pdo->query(
    'SELECT h.habitacion_id, h.numero, th.capacidad, th.precio_noche, th.nombre AS tipo_habitacion
     FROM habitaciones h
     INNER JOIN tipos_habitaciones th ON th.tipo_habitacion_id = h.tipo_habitacion_id
     INNER JOIN estados_habitaciones eh ON eh.estado_habitacion_id = h.estado_habitacion_id
     WHERE eh.nombre NOT IN (\'Mantenimiento\', \'Limpieza\')
     ORDER BY h.numero'
)->fetchAll();
$reservation = [
    'habitacion_id' => '',
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
        $fieldInput = $_POST[$field] ?? '';
        if (!is_string($fieldInput)) {
            $validFieldTypes = false;
            $fieldInput = '';
        }
        $reservation[$field] = trim($fieldInput);
    }
    $roomId = filter_var(
        $reservation['habitacion_id'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    $guestCount = filter_var(
        $reservation['numero_huespedes'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    $checkIn = DateTimeImmutable::createFromFormat('!Y-m-d', $reservation['fecha_entrada']);
    $checkOut = DateTimeImmutable::createFromFormat('!Y-m-d', $reservation['fecha_salida']);
    $validDates = $checkIn instanceof DateTimeImmutable
        && $checkOut instanceof DateTimeImmutable
        && $checkIn->format('Y-m-d') === $reservation['fecha_entrada']
        && $checkOut->format('Y-m-d') === $reservation['fecha_salida']
        && $checkIn >= new DateTimeImmutable('today')
        && $checkOut > $checkIn;

    if (!$validFieldTypes || $roomId === false || $guestCount === false
        || !$validDates || strlen($reservation['observaciones']) > 255) {
        $errorMessage = 'Verifica la habitación, las fechas, el número de huéspedes y las observaciones.';
    } else {
        try {
            $pdo->beginTransaction();
            $pendingStateStatement = $pdo->prepare(
                'SELECT estado_reserva_id
                 FROM estados_reservas
                 WHERE nombre = :nombre AND activo = 1
                 LIMIT 1 FOR UPDATE'
            );
            $pendingStateStatement->execute(['nombre' => 'Pendiente']);
            $pendingStateId = $pendingStateStatement->fetchColumn();

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

            if (!$pendingStateId) {
                $pdo->rollBack();
                $errorMessage = 'No hay un estado Pendiente activo para crear reservas. Contacta al hotel.';
            } elseif (!$room || in_array($room['estado'], ['Mantenimiento', 'Limpieza'], true)
                || (int) $guestCount > (int) $room['capacidad']
                || ($reservation['fecha_entrada'] <= date('Y-m-d') && $room['estado'] !== 'Disponible')) {
                $pdo->rollBack();
                $errorMessage = 'La habitación no está disponible o no tiene capacidad suficiente.';
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
                    $errorMessage = 'La habitación ya está reservada para esas fechas. Elige otra fecha o habitación.';
                } else {
                    $insertStatement = $pdo->prepare(
                        'INSERT INTO reservas (
                            cliente_id, habitacion_id, empleado_id, estado_reserva_id,
                            fecha_entrada, fecha_salida, numero_huespedes, precio_noche, observaciones
                         ) VALUES (
                            :cliente_id, :habitacion_id, NULL, :estado_reserva_id,
                            :fecha_entrada, :fecha_salida, :numero_huespedes, :precio_noche, :observaciones
                         )'
                    );
                    $insertStatement->execute([
                        'cliente_id' => $clientId,
                        'habitacion_id' => $roomId,
                        'estado_reserva_id' => $pendingStateId,
                        'fecha_entrada' => $reservation['fecha_entrada'],
                        'fecha_salida' => $reservation['fecha_salida'],
                        'numero_huespedes' => $guestCount,
                        'precio_noche' => $room['precio_noche'],
                        'observaciones' => $reservation['observaciones'] !== ''
                            ? $reservation['observaciones']
                            : null,
                    ]);
                    $pdo->commit();
                    $_SESSION['reservas_mensaje'] = 'Tu solicitud de reserva se envió y está pendiente de confirmación.';
                    redirect_to('/assets/modules/reservas/mis_reservas.php');
                }
            }
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Hotel Manager customer reservation creation failed: ' . $exception->getMessage());
            $errorMessage = 'No se pudo enviar la reserva. Inténtalo de nuevo o contacta al hotel.';
        }
    }
}

$reservationStatement = $pdo->prepare(
    'SELECT r.reserva_id, r.fecha_reserva, r.fecha_entrada, r.fecha_salida,
            r.numero_huespedes, r.precio_noche, r.observaciones,
            h.numero AS habitacion, th.nombre AS tipo_habitacion,
            er.nombre AS estado
     FROM reservas r
     INNER JOIN habitaciones h ON h.habitacion_id = r.habitacion_id
     INNER JOIN tipos_habitaciones th ON th.tipo_habitacion_id = h.tipo_habitacion_id
     INNER JOIN estados_reservas er ON er.estado_reserva_id = r.estado_reserva_id
     WHERE r.cliente_id = :cliente_id
     ORDER BY r.fecha_entrada DESC, r.reserva_id DESC'
);
$reservationStatement->execute(['cliente_id' => $clientId]);
$reservations = $reservationStatement->fetchAll();
$successMessage = $_SESSION['reservas_mensaje'] ?? '';
$sessionErrorMessage = $_SESSION['reservas_error'] ?? '';
unset($_SESSION['reservas_mensaje'], $_SESSION['reservas_error']);
if ($errorMessage === '') {
    $errorMessage = $sessionErrorMessage;
}
$baseUrl = htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8');
$tituloCabecera = 'Mis reservas';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/../../includes/favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/estilos.css">
    <title>Mis reservas | Hotel Premium</title>
</head>
<body class="panel">
    <div class="panel-panel">
        <?php require __DIR__ . '/../../includes/panel_cabecera.php'; ?>
        <div class="panel-distribucion">
            <?php require __DIR__ . '/../../includes/sidebar.php'; ?>
            <main class="panel-contenido pagina-habitaciones">
                <header class="habitaciones-cabecera">
                    <p>Solicita una habitación y consulta el estado de tus reservas.</p>
                </header>

                <?php if ($successMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-exito" role="status"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if ($errorMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-error" role="alert"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <?php if ($rooms === []): ?>
                    <p class="mensaje-info">No hay habitaciones que puedan reservarse en este momento.</p>
                <?php endif; ?>
                <form class="formulario-habitacion" method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                    <label for="habitacion_id">Habitación</label>
                    <select id="habitacion_id" name="habitacion_id" required <?= $rooms === [] ? 'disabled' : '' ?>>
                        <option value="">Selecciona una habitación</option>
                        <?php foreach ($rooms as $room): ?>
                            <option
                                value="<?= (int) $room['habitacion_id'] ?>"
                                <?= (string) $room['habitacion_id'] === $reservation['habitacion_id'] ? 'selected' : '' ?>
                            ><?= htmlspecialchars('Habitación ' . $room['numero'] . ' — ' . $room['tipo_habitacion'] . ' — capacidad ' . $room['capacidad'] . ' — ' . number_format((float) $room['precio_noche'], 2, ',', '.') . ' por noche', ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="fecha_entrada">Fecha de entrada</label>
                    <input id="fecha_entrada" name="fecha_entrada" type="date" min="<?= date('Y-m-d') ?>" required value="<?= htmlspecialchars($reservation['fecha_entrada'], ENT_QUOTES, 'UTF-8') ?>">

                    <label for="fecha_salida">Fecha de salida</label>
                    <input id="fecha_salida" name="fecha_salida" type="date" min="<?= date('Y-m-d') ?>" required value="<?= htmlspecialchars($reservation['fecha_salida'], ENT_QUOTES, 'UTF-8') ?>">

                    <label for="numero_huespedes">Número de huéspedes</label>
                    <input id="numero_huespedes" name="numero_huespedes" type="number" min="1" step="1" required value="<?= htmlspecialchars($reservation['numero_huespedes'], ENT_QUOTES, 'UTF-8') ?>">

                    <label for="observaciones">Observaciones (opcional)</label>
                    <textarea id="observaciones" name="observaciones" maxlength="255" rows="4"><?= htmlspecialchars($reservation['observaciones'], ENT_QUOTES, 'UTF-8') ?></textarea>

                    <p class="ayuda-formulario">La disponibilidad y capacidad se validan al enviar la solicitud. La reserva quedará pendiente hasta que el hotel la confirme.</p>
                    <button class="boton-accion" type="submit" <?= $rooms === [] ? 'disabled' : '' ?>>Solicitar reserva</button>
                </form>

                <h2>Historial de reservas</h2>
                <?php if ($reservations === []): ?>
                    <p class="mensaje-info">Todavía no tienes reservas.</p>
                <?php else: ?>
                    <div class="tabla-contenedor">
                        <table class="tabla-habitaciones">
                            <thead>
                                <tr>
                                    <th>Habitación</th>
                                    <th>Entrada</th>
                                    <th>Salida</th>
                                    <th>Huéspedes</th>
                                    <th>Precio por noche</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservations as $customerReservation): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($customerReservation['habitacion'] . ' — ' . $customerReservation['tipo_habitacion'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($customerReservation['fecha_entrada'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($customerReservation['fecha_salida'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= (int) $customerReservation['numero_huespedes'] ?></td>
                                        <td><?= htmlspecialchars(number_format((float) $customerReservation['precio_noche'], 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($customerReservation['estado'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <?php if ($customerReservation['estado'] === 'Pendiente'): ?>
                                                <form
                                                    action="<?= $baseUrl ?>/assets/modules/reservas/cancelar.php"
                                                    method="post"
                                                    onsubmit="return confirm('¿Deseas cancelar esta solicitud de reserva?');"
                                                >
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="reserva_id" value="<?= (int) $customerReservation['reserva_id'] ?>">
                                                    <button class="boton-peligro" type="submit">Cancelar</button>
                                                </form>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
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
