<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';
require_role($pdo, 'Administrador');

$paymentStateIdInput = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['estado_pago_id'] ?? '')
    : ($_GET['id'] ?? '');
$paymentStateId = is_string($paymentStateIdInput)
    ? filter_var($paymentStateIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
if ($paymentStateId === false) {
    http_response_code(400);
    exit('ID de estado de pago inválido.');
}

$stateStatement = $pdo->prepare(
    'SELECT estado_pago_id, nombre, descripcion
     FROM estados_pagos
     WHERE estado_pago_id = :estado_pago_id
     LIMIT 1'
);
$stateStatement->execute(['estado_pago_id' => $paymentStateId]);
$paymentState = $stateStatement->fetch();
if (!$paymentState) {
    http_response_code(404);
    exit('No se encontró el estado de pago solicitado.');
}

$name = $paymentState['nombre'];
$description = $paymentState['descripcion'] ?? '';
$isSystemState = in_array($paymentState['nombre'], ['Pendiente', 'Pagado', 'Reembolsado', 'Cancelado'], true);
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Solicitud inválida. Vuelve a cargar el formulario e inténtalo de nuevo.');
    }

    $nameInput = $_POST['nombre'] ?? '';
    $descriptionInput = $_POST['descripcion'] ?? '';
    if (!is_string($nameInput) || !is_string($descriptionInput)) {
        $errorMessage = 'Los datos enviados no son válidos.';
    } else {
        $name = trim($nameInput);
        $description = trim($descriptionInput);

        if ($name === '' || strlen($name) > 50 || strlen($description) > 150) {
            $errorMessage = 'El nombre es obligatorio (máximo 50 caracteres) y la descripción admite hasta 150 caracteres.';
        } elseif ($isSystemState && $name !== $paymentState['nombre']) {
            $errorMessage = 'No se puede cambiar el nombre de este estado porque el flujo de pagos depende de él.';
        } else {
            try {
                $updateStatement = $pdo->prepare(
                    'UPDATE estados_pagos
                     SET nombre = :nombre, descripcion = :descripcion
                     WHERE estado_pago_id = :estado_pago_id'
                );
                $updateStatement->execute([
                    'nombre' => $name,
                    'descripcion' => $description !== '' ? $description : null,
                    'estado_pago_id' => $paymentStateId,
                ]);

                $_SESSION['estados_pagos_mensaje'] = 'Los cambios del estado de pago se guardaron correctamente.';
                redirect_to('/assets/modules/estados_pagos/index.php');
            } catch (PDOException $exception) {
                error_log('Hotel Manager payment state update failed: ' . $exception->getMessage());
                $errorMessage = 'No se pudieron guardar los cambios. Verifica que el nombre no esté duplicado.';
            }
        }
    }
}

$baseUrl = htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8');
$tituloCabecera = 'Editar estado de pago';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/../../includes/favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/estilos.css">
    <title>Editar estado de pago | Hotel Premium</title>
</head>
<body class="panel">
    <div class="panel-panel">
        <?php require __DIR__ . '/../../includes/panel_cabecera.php'; ?>
        <div class="panel-distribucion">
            <?php require __DIR__ . '/../../includes/sidebar.php'; ?>
            <main class="panel-contenido pagina-habitaciones">
                <header class="habitaciones-cabecera">
                    <p>Actualiza el nombre o la descripción del estado de pago.</p>
                    <a class="boton-secundario" href="<?= $baseUrl ?>/assets/modules/estados_pagos/index.php">Volver a estados</a>
                </header>

                <?php if ($errorMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if ($isSystemState): ?>
                    <p class="mensaje-info">Este nombre está protegido porque el flujo de pagos depende de él; puedes cambiar su descripción.</p>
                <?php endif; ?>

                <form class="formulario-habitacion" method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="estado_pago_id" value="<?= (int) $paymentStateId ?>">

                    <label for="nombre">Nombre</label>
                    <?php if ($isSystemState): ?>
                        <input type="hidden" name="nombre" value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>">
                        <input id="nombre" type="text" value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" disabled>
                    <?php else: ?>
                        <input id="nombre" name="nombre" type="text" maxlength="50" required value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>">
                    <?php endif; ?>

                    <label for="descripcion">Descripción (opcional)</label>
                    <textarea id="descripcion" name="descripcion" maxlength="150" rows="3"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></textarea>

                    <button class="boton-accion" type="submit">Guardar cambios</button>
                </form>
            </main>
        </div>
    </div>
</body>
</html>