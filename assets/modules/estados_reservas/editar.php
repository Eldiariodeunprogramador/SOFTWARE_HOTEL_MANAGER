<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';
require_role($pdo, 'Administrador');

$reservationStateIdInput = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['estado_reserva_id'] ?? '')
    : ($_GET['id'] ?? '');
$reservationStateId = is_string($reservationStateIdInput)
    ? filter_var($reservationStateIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
if ($reservationStateId === false) {
    http_response_code(400);
    exit('ID de estado de reserva inválido.');
}

$stateStatement = $pdo->prepare(
    'SELECT estado_reserva_id, nombre, descripcion, activo
     FROM estados_reservas
     WHERE estado_reserva_id = :estado_reserva_id
     LIMIT 1'
);
$stateStatement->execute(['estado_reserva_id' => $reservationStateId]);
$reservationState = $stateStatement->fetch();
if (!$reservationState) {
    http_response_code(404);
    exit('No se encontró el estado de reserva solicitado.');
}

$name = $reservationState['nombre'];
$description = $reservationState['descripcion'] ?? '';
$active = (bool) $reservationState['activo'];
$isSystemState = in_array($reservationState['nombre'], ['Pendiente', 'Confirmada', 'Cancelada'], true);
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Solicitud inválida. Vuelve a cargar el formulario e inténtalo de nuevo.');
    }

    $nameInput = $_POST['nombre'] ?? '';
    $descriptionInput = $_POST['descripcion'] ?? '';
    $activeInput = $_POST['activo'] ?? '';
    if (!is_string($nameInput) || !is_string($descriptionInput) || !is_string($activeInput)) {
        $errorMessage = 'Los datos enviados no son válidos.';
    } else {
        $name = trim($nameInput);
        $description = trim($descriptionInput);
        $active = $activeInput === '1';

        if ($isSystemState && ($name !== $reservationState['nombre'] || $activeInput !== '1')) {
            $errorMessage = 'Los estados Pendiente, Confirmada y Cancelada son necesarios para el flujo de reservas y deben permanecer activos.';
        } elseif ($name === '' || strlen($name) > 50 || strlen($description) > 150
            || !in_array($activeInput, ['0', '1'], true)) {
            $errorMessage = 'El nombre es obligatorio (máximo 50 caracteres), la descripción admite 150 y el estado enviado debe ser válido.';
        } else {
            try {
                $pdo->beginTransaction();
                if ($activeInput === '0' && (bool) $reservationState['activo']) {
                    $activeStatesStatement = $pdo->query(
                        'SELECT estado_reserva_id
                         FROM estados_reservas
                         WHERE activo = 1
                         FOR UPDATE'
                    );
                    if (count($activeStatesStatement->fetchAll()) <= 1) {
                        $pdo->rollBack();
                        $errorMessage = 'Debe permanecer al menos un estado de reserva activo.';
                    }
                }

                if ($errorMessage === '') {
                    $updateStatement = $pdo->prepare(
                        'UPDATE estados_reservas
                         SET nombre = :nombre, descripcion = :descripcion, activo = :activo
                         WHERE estado_reserva_id = :estado_reserva_id'
                    );
                    $updateStatement->execute([
                        'nombre' => $name,
                        'descripcion' => $description !== '' ? $description : null,
                        'activo' => $active ? 1 : 0,
                        'estado_reserva_id' => $reservationStateId,
                    ]);
                    $pdo->commit();
                    $_SESSION['estados_reservas_mensaje'] = 'Los cambios del estado de reserva se guardaron correctamente.';
                    redirect_to('/assets/modules/estados_reservas/index.php');
                }
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Hotel Manager reservation state update failed: ' . $exception->getMessage());
                $errorMessage = 'No se pudieron guardar los cambios. Verifica que el nombre no esté duplicado.';
            }
        }
    }
}

$baseUrl = htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8');
$tituloCabecera = 'Editar estado de reserva';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/../../includes/favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/estilos.css">
    <title>Editar estado de reserva | Hotel Premium</title>
</head>
<body class="panel">
    <div class="panel-panel">
        <?php require __DIR__ . '/../../includes/panel_cabecera.php'; ?>
        <div class="panel-distribucion">
            <?php require __DIR__ . '/../../includes/sidebar.php'; ?>
            <main class="panel-contenido pagina-habitaciones">
                <header class="habitaciones-cabecera">
                    <p>Actualiza el nombre, la descripción y la disponibilidad del estado.</p>
                    <a class="boton-secundario" href="<?= $baseUrl ?>/assets/modules/estados_reservas/index.php">Volver a estados</a>
                </header>

                <?php if ($errorMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <form class="formulario-habitacion" method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="estado_reserva_id" value="<?= (int) $reservationStateId ?>">

                    <label for="nombre">Nombre</label>
                    <input id="nombre" name="nombre" type="text" maxlength="50" required value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>">

                    <label for="descripcion">Descripción (opcional)</label>
                    <textarea id="descripcion" name="descripcion" maxlength="150" rows="3"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></textarea>

                    <label for="activo">Disponibilidad</label>
                    <?php if ($isSystemState): ?>
                        <input type="hidden" name="activo" value="1">
                        <input id="activo" type="text" value="Activo (requerido)" disabled>
                    <?php else: ?>
                        <select id="activo" name="activo" required>
                            <option value="1" <?= $active ? 'selected' : '' ?>>Activo</option>
                            <option value="0" <?= !$active ? 'selected' : '' ?>>Inactivo</option>
                        </select>
                    <?php endif; ?>

                    <button class="boton-accion" type="submit">Guardar cambios</button>
                </form>
            </main>
        </div>
    </div>
</body>
</html>
