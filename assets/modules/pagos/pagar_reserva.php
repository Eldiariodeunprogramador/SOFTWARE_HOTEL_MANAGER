<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('pagos.registrar');
require_once __DIR__ . '/../../config/database.php';

$reservationIdInput = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['reserva_id'] ?? '')
    : ($_GET['id'] ?? '');
$reservationId = $reservationIdInput === ''
    ? null
    : (is_string($reservationIdInput)
        ? filter_var($reservationIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        : false);
if ($reservationId === false || ($_SERVER['REQUEST_METHOD'] === 'POST' && $reservationId === null)) {
    http_response_code(400);
    exit('ID de reserva inválido.');
}

$paymentMethods = $pdo->query(
    'SELECT metodo_pago_id, nombre
     FROM metodos_pagos
     WHERE activo = 1
     ORDER BY nombre'
)->fetchAll();
$paymentMethodIds = array_map('intval', array_column($paymentMethods, 'metodo_pago_id'));
$reservationBalanceSql =
    'SELECT r.reserva_id, r.fecha_entrada, r.fecha_salida, r.precio_noche,
            DATEDIFF(r.fecha_salida, r.fecha_entrada) AS noches,
            ROUND(r.precio_noche * DATEDIFF(r.fecha_salida, r.fecha_entrada), 2) AS total_reserva,
            COALESCE(pt.pagado_neto, 0) AS pagado_neto,
            LEAST(
                ROUND(r.precio_noche * DATEDIFF(r.fecha_salida, r.fecha_entrada), 2),
                GREATEST(
                    0,
                    ROUND(
                        r.precio_noche * DATEDIFF(r.fecha_salida, r.fecha_entrada)
                        - COALESCE(pt.pagado_neto, 0),
                        2
                    )
                )
            ) AS saldo_pendiente,
            er.nombre AS estado_reserva,
            CONCAT(persona.nombres, \' \', persona.apellidos) AS cliente,
            h.numero AS habitacion
     FROM reservas r
     INNER JOIN estados_reservas er ON er.estado_reserva_id = r.estado_reserva_id
     INNER JOIN clientes c ON c.cliente_id = r.cliente_id
     INNER JOIN personas persona ON persona.persona_id = c.persona_id
     INNER JOIN habitaciones h ON h.habitacion_id = r.habitacion_id
     LEFT JOIN (
         SELECT p.reserva_id,
                SUM(
                    CASE
                        WHEN ep.nombre = \'Pagado\' THEN p.monto
                        WHEN ep.nombre = \'Reembolsado\' THEN -p.monto
                        ELSE 0
                    END
                ) AS pagado_neto
         FROM pagos p
         INNER JOIN estados_pagos ep ON ep.estado_pago_id = p.estado_pago_id
         GROUP BY p.reserva_id
     ) pt ON pt.reserva_id = r.reserva_id';
$errorMessage = '';
$successMessage = $_SESSION['pagos_mensaje'] ?? '';
unset($_SESSION['pagos_mensaje']);
$reference = '';
$observations = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Solicitud inválida. Vuelve a cargar el formulario e inténtalo de nuevo.');
    }

    $methodIdInput = $_POST['metodo_pago_id'] ?? '';
    $referenceInput = $_POST['referencia'] ?? '';
    $observationsInput = $_POST['observaciones'] ?? '';
    if (!is_string($methodIdInput) || !is_string($referenceInput) || !is_string($observationsInput)) {
        $errorMessage = 'Los datos enviados no son válidos.';
    } else {
        $methodId = filter_var($methodIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $reference = trim($referenceInput);
        $observations = trim($observationsInput);
        if ($methodId === false || !in_array((int) $methodId, $paymentMethodIds, true)
            || strlen($reference) > 100 || strlen($observations) > 255) {
            $errorMessage = 'Selecciona un método activo y verifica que la referencia y las observaciones respeten sus límites.';
        } else {
            try {
                $pdo->beginTransaction();
                $reservationLockStatement = $pdo->prepare(
                    'SELECT r.reserva_id, er.nombre AS estado_reserva
                     FROM reservas r
                     INNER JOIN estados_reservas er ON er.estado_reserva_id = r.estado_reserva_id
                     WHERE r.reserva_id = :reserva_id
                     FOR UPDATE'
                );
                $reservationLockStatement->execute(['reserva_id' => $reservationId]);
                $lockedReservation = $reservationLockStatement->fetch();
                if (!$lockedReservation) {
                    $pdo->rollBack();
                    http_response_code(404);
                    exit('No se encontró la reserva solicitada.');
                }
                if ($lockedReservation['estado_reserva'] === 'Cancelada') {
                    $pdo->rollBack();
                    $errorMessage = 'No se pueden registrar pagos para una reserva cancelada.';
                } else {
                    $methodLockStatement = $pdo->prepare(
                        'SELECT metodo_pago_id
                         FROM metodos_pagos
                         WHERE metodo_pago_id = :metodo_pago_id AND activo = 1
                         FOR UPDATE'
                    );
                    $methodLockStatement->execute(['metodo_pago_id' => $methodId]);
                    if (!$methodLockStatement->fetchColumn()) {
                        $pdo->rollBack();
                        $errorMessage = 'El método de pago ya no está disponible.';
                    } else {
                        $paidStateStatement = $pdo->prepare(
                            'SELECT estado_pago_id
                             FROM estados_pagos
                             WHERE nombre = :nombre
                             LIMIT 1
                             FOR UPDATE'
                        );
                        $paidStateStatement->execute(['nombre' => 'Pagado']);
                        $paidStateId = $paidStateStatement->fetchColumn();
                        if (!$paidStateId) {
                            $pdo->rollBack();
                            $errorMessage = 'No existe el estado Pagado. Verifica los estados de pago configurados.';
                        } else {
                            $balanceStatement = $pdo->prepare(
                                $reservationBalanceSql . ' WHERE r.reserva_id = :reserva_id'
                            );
                            $balanceStatement->execute(['reserva_id' => $reservationId]);
                            $reservationBalance = $balanceStatement->fetch();
                            $balance = $reservationBalance
                                ? $reservationBalance['saldo_pendiente']
                                : '0.00';
                            if (!$reservationBalance || (float) $balance <= 0) {
                                $pdo->rollBack();
                                $errorMessage = 'La reserva no tiene saldo pendiente para pagar.';
                            } else {
                                $insertStatement = $pdo->prepare(
                                    'INSERT INTO pagos
                                        (reserva_id, metodo_pago_id, estado_pago_id, monto, referencia, observaciones)
                                     VALUES
                                        (:reserva_id, :metodo_pago_id, :estado_pago_id, :monto, :referencia, :observaciones)'
                                );
                                $insertStatement->execute([
                                    'reserva_id' => $reservationId,
                                    'metodo_pago_id' => $methodId,
                                    'estado_pago_id' => $paidStateId,
                                    'monto' => $balance,
                                    'referencia' => $reference !== '' ? $reference : null,
                                    'observaciones' => $observations !== '' ? $observations : null,
                                ]);
                                $pdo->commit();
                                $_SESSION['pagos_mensaje'] = 'El pago se registró y confirmó correctamente.';
                                redirect_to('/assets/modules/pagos/pagar_reserva.php?id=' . (int) $reservationId);
                            }
                        }
                    }
                }
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Hotel Manager reservation payment failed: ' . $exception->getMessage());
                $errorMessage = 'No se pudo registrar el pago. Verifica que la reserva y el método de pago sigan disponibles.';
            }
        }
    }
}

$selectedReservation = null;
if ($reservationId !== null) {
    $selectedReservationStatement = $pdo->prepare(
        $reservationBalanceSql . ' WHERE r.reserva_id = :reserva_id'
    );
    $selectedReservationStatement->execute(['reserva_id' => $reservationId]);
    $selectedReservation = $selectedReservationStatement->fetch();
    if (!$selectedReservation) {
        http_response_code(404);
        exit('No se encontró la reserva solicitada.');
    }
}

$reservations = $pdo->query(
    $reservationBalanceSql . ' ORDER BY r.fecha_entrada DESC, r.reserva_id DESC'
)->fetchAll();
$canViewPayments = user_has_permission($pdo, 'pagos.ver');
$baseUrl = htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8');
$tituloCabecera = 'Pagar reserva';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/../../includes/favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/estilos.css">
    <title>Pagar reserva | Hotel Premium</title>
</head>
<body class="panel">
    <div class="panel-panel">
        <?php require __DIR__ . '/../../includes/panel_cabecera.php'; ?>
        <div class="panel-distribucion">
            <?php require __DIR__ . '/../../includes/sidebar.php'; ?>
            <main class="panel-contenido pagina-habitaciones">
                <header class="habitaciones-cabecera">
                    <p>Consulta las reservas y registra el pago total del saldo pendiente.</p>
                    <?php if ($canViewPayments): ?>
                        <a class="boton-secundario" href="<?= $baseUrl ?>/assets/modules/pagos/index.php">Volver a pagos</a>
                    <?php else: ?>
                        <a class="boton-secundario" href="<?= $baseUrl ?>/assets/modules/dashboard/index.php">Volver al panel</a>
                    <?php endif; ?>
                </header>

                <?php if ($successMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-exito"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if ($errorMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <?php if ($selectedReservation !== null): ?>
                    <h2>Registrar pago de la reserva #<?= (int) $selectedReservation['reserva_id'] ?></h2>
                    <p>
                        Cliente: <?= htmlspecialchars($selectedReservation['cliente'], ENT_QUOTES, 'UTF-8') ?> |
                        Habitación: <?= htmlspecialchars($selectedReservation['habitacion'], ENT_QUOTES, 'UTF-8') ?> |
                        Saldo pendiente: <?= htmlspecialchars(number_format((float) $selectedReservation['saldo_pendiente'], 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?>
                    </p>
                    <?php if ($selectedReservation['estado_reserva'] === 'Cancelada'): ?>
                        <p class="mensaje-registro mensaje-error">No se pueden registrar pagos para una reserva cancelada.</p>
                    <?php elseif ((float) $selectedReservation['saldo_pendiente'] <= 0): ?>
                        <p class="mensaje-info">La reserva no tiene saldo pendiente.</p>
                    <?php elseif ($paymentMethods === []): ?>
                        <p class="mensaje-registro mensaje-error">No hay métodos de pago activos disponibles.</p>
                    <?php else: ?>
                        <form class="formulario-habitacion" method="post">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="reserva_id" value="<?= (int) $selectedReservation['reserva_id'] ?>">

                            <label for="metodo_pago_id">Método de pago</label>
                            <select id="metodo_pago_id" name="metodo_pago_id" required>
                                <option value="">Selecciona un método</option>
                                <?php foreach ($paymentMethods as $method): ?>
                                    <option value="<?= (int) $method['metodo_pago_id'] ?>" <?= isset($methodId) && (string) $method['metodo_pago_id'] === (string) $methodId ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($method['nombre'], ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>

                            <label for="referencia">Referencia (opcional)</label>
                            <input id="referencia" name="referencia" type="text" maxlength="100" value="<?= htmlspecialchars($reference, ENT_QUOTES, 'UTF-8') ?>">

                            <label for="observaciones">Observaciones (opcional)</label>
                            <textarea id="observaciones" name="observaciones" maxlength="255" rows="3"><?= htmlspecialchars($observations, ENT_QUOTES, 'UTF-8') ?></textarea>

                            <button class="boton-accion" type="submit">
                                Registrar pago de <?= htmlspecialchars(number_format((float) $selectedReservation['saldo_pendiente'], 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?>
                            </button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>

                <h2>Reservas y saldo pendiente</h2>
                <?php if ($reservations === []): ?>
                    <p class="mensaje-info">No hay reservas registradas.</p>
                <?php else: ?>
                    <div class="tabla-contenedor">
                        <table class="tabla-registros">
                            <thead>
                                <tr>
                                    <th>Reserva</th>
                                    <th>Cliente</th>
                                    <th>Habitación</th>
                                    <th>Fechas</th>
                                    <th>Noches</th>
                                    <th>Total</th>
                                    <th>Pagado</th>
                                    <th>Saldo</th>
                                    <th>Estado de reserva</th>
                                    <th>Acción</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservations as $reservation): ?>
                                    <tr>
                                        <td>#<?= (int) $reservation['reserva_id'] ?></td>
                                        <td><?= htmlspecialchars($reservation['cliente'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($reservation['habitacion'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <?= htmlspecialchars($reservation['fecha_entrada'], ENT_QUOTES, 'UTF-8') ?>
                                            a <?= htmlspecialchars($reservation['fecha_salida'], ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td><?= (int) $reservation['noches'] ?></td>
                                        <td><?= htmlspecialchars(number_format((float) $reservation['total_reserva'], 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars(number_format((float) $reservation['pagado_neto'], 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars(number_format((float) $reservation['saldo_pendiente'], 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($reservation['estado_reserva'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <?php if ($reservation['estado_reserva'] === 'Cancelada'): ?>
                                                <span class="ayuda-formulario">Reserva cancelada</span>
                                            <?php elseif ((float) $reservation['saldo_pendiente'] > 0): ?>
                                                <a
                                                    class="boton-secundario"
                                                    href="<?= $baseUrl ?>/assets/modules/pagos/pagar_reserva.php?id=<?= (int) $reservation['reserva_id'] ?>"
                                                >Pagar saldo</a>
                                            <?php else: ?>
                                                <span class="ayuda-formulario">Saldo cubierto</span>
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