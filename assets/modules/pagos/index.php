<?php
require_once __DIR__ . '/../../includes/auth.php';
require_permission('pagos.ver');
require_once __DIR__ . '/../../config/database.php';

$statement = $pdo->query(
    'SELECT p.pago_id, p.monto, p.fecha_pago, p.referencia, p.observaciones,
            p.reserva_id, r.fecha_entrada, r.fecha_salida,
            CONCAT(persona.nombres, \' \', persona.apellidos) AS cliente,
            h.numero AS habitacion,
            mp.nombre AS metodo_pago,
            ep.nombre AS estado_pago
     FROM pagos p
     INNER JOIN reservas r ON r.reserva_id = p.reserva_id
     INNER JOIN clientes c ON c.cliente_id = r.cliente_id
     INNER JOIN personas persona ON persona.persona_id = c.persona_id
     INNER JOIN habitaciones h ON h.habitacion_id = r.habitacion_id
     INNER JOIN metodos_pagos mp ON mp.metodo_pago_id = p.metodo_pago_id
     INNER JOIN estados_pagos ep ON ep.estado_pago_id = p.estado_pago_id
     ORDER BY p.fecha_pago DESC, p.pago_id DESC'
);
$payments = $statement->fetchAll();
$successMessage = $_SESSION['pagos_mensaje'] ?? '';
$errorMessage = $_SESSION['pagos_error'] ?? '';
unset($_SESSION['pagos_mensaje'], $_SESSION['pagos_error']);
$canCancel = user_has_role($pdo, 'Administrador');
$canRegister = user_has_permission($pdo, 'pagos.registrar');
$baseUrl = htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8');
$tituloCabecera = 'Pagos';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/../../includes/favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/estilos.css">
    <title>Pagos | Hotel Premium</title>
</head>
<body class="panel">
    <div class="panel-panel">
        <?php require __DIR__ . '/../../includes/panel_cabecera.php'; ?>
        <div class="panel-distribucion">
            <?php require __DIR__ . '/../../includes/sidebar.php'; ?>
            <main class="panel-contenido pagina-habitaciones">
                <header class="habitaciones-cabecera">
                    <p>Consulta los pagos asociados a reservas, con su método y estado.</p>
                    <?php if ($canRegister): ?>
                        <a class="boton-accion" href="<?= $baseUrl ?>/assets/modules/pagos/pagar_reserva.php">
                            <i class="fa-solid fa-money-bill-transfer" aria-hidden="true"></i>
                            Pagar reserva
                        </a>
                    <?php endif; ?>
                </header>

                <?php if ($successMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-exito"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if ($errorMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <?php if ($payments === []): ?>
                    <p class="mensaje-info">No hay pagos registrados.</p>
                <?php else: ?>
                    <div class="tabla-contenedor">
                        <table class="tabla-registros">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Reserva</th>
                                    <th>Cliente</th>
                                    <th>Habitación</th>
                                    <th>Método</th>
                                    <th>Monto</th>
                                    <th>Referencia</th>
                                    <th>Estado</th>
                                    <th>Observaciones</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($payment['fecha_pago'] ?? 'No indicada', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            #<?= (int) $payment['reserva_id'] ?>
                                            (<?= htmlspecialchars($payment['fecha_entrada'], ENT_QUOTES, 'UTF-8') ?>
                                            a <?= htmlspecialchars($payment['fecha_salida'], ENT_QUOTES, 'UTF-8') ?>)
                                        </td>
                                        <td><?= htmlspecialchars($payment['cliente'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($payment['habitacion'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($payment['metodo_pago'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars(number_format((float) $payment['monto'], 2, ',', '.'), ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($payment['referencia'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($payment['estado_pago'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($payment['observaciones'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <?php if ($canCancel && $payment['estado_pago'] === 'Pendiente'): ?>
                                                <form
                                                    action="<?= $baseUrl ?>/assets/modules/pagos/cancelar.php"
                                                    method="post"
                                                    onsubmit="return confirm('¿Deseas cancelar este pago pendiente?');"
                                                >
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="pago_id" value="<?= (int) $payment['pago_id'] ?>">
                                                    <button class="boton-peligro" type="submit">Cancelar</button>
                                                </form>
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