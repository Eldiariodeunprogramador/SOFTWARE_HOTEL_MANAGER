<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../config/database.php';
require_role($pdo, 'Administrador');

$roomStateIdInput = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['estado_habitacion_id'] ?? '')
    : ($_GET['id'] ?? '');
$roomStateId = filter_var(
    $roomStateIdInput,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
if ($roomStateId === false) {
    http_response_code(400);
    exit('ID de estado de habitación inválido.');
}

$roomStateStatement = $pdo->prepare(
    'SELECT estado_habitacion_id, nombre
     FROM estados_habitaciones
     WHERE estado_habitacion_id = :estado_habitacion_id
     LIMIT 1'
);
$roomStateStatement->execute(['estado_habitacion_id' => $roomStateId]);
$roomState = $roomStateStatement->fetch();
if (!$roomState) {
    http_response_code(404);
    exit('No se encontró el estado de habitación solicitado.');
}

$name = $roomState['nombre'];
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Solicitud inválida. Vuelve a cargar el formulario e inténtalo de nuevo.');
    }

    $nameInput = $_POST['nombre'] ?? '';
    if (!is_string($nameInput)) {
        $errorMessage = 'El nombre enviado no es válido.';
    } else {
        $name = trim($nameInput);
        if ($name === '' || strlen($name) > 50) {
            $errorMessage = 'El nombre es obligatorio y admite hasta 50 caracteres.';
        } else {
            try {
                $updateStatement = $pdo->prepare(
                    'UPDATE estados_habitaciones
                     SET nombre = :nombre
                     WHERE estado_habitacion_id = :estado_habitacion_id'
                );
                $updateStatement->execute([
                    'nombre' => $name,
                    'estado_habitacion_id' => $roomStateId,
                ]);

                $_SESSION['estados_habitaciones_mensaje'] = 'Los cambios del estado de habitación se guardaron correctamente.';
                redirect_to('/assets/modules/habitaciones/estados_habitaciones/index.php');
            } catch (PDOException $exception) {
                error_log('Hotel Manager room state update failed: ' . $exception->getMessage());
                $errorMessage = 'No se pudieron guardar los cambios. Verifica que el nombre no esté duplicado.';
            }
        }
    }
}

$baseUrl = htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8');
$tituloCabecera = 'Editar estado de habitación';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/../../../includes/favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/estilos.css">
    <title>Editar estado de habitación | Hotel Premium</title>
</head>
<body class="panel">
    <div class="panel-panel">
        <?php require __DIR__ . '/../../../includes/panel_cabecera.php'; ?>
        <div class="panel-distribucion">
            <?php require __DIR__ . '/../../../includes/sidebar.php'; ?>
            <main class="panel-contenido pagina-habitaciones">
                <header class="habitaciones-cabecera">
                    <p>Actualiza el nombre del estado de habitación.</p>
                    <a class="boton-secundario" href="<?= $baseUrl ?>/assets/modules/habitaciones/estados_habitaciones/index.php">Volver a estados</a>
                </header>

                <?php if ($errorMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <form class="formulario-habitacion" method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="estado_habitacion_id" value="<?= (int) $roomStateId ?>">

                    <label for="nombre">Nombre</label>
                    <input id="nombre" name="nombre" type="text" maxlength="50" required value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>">

                    <button class="boton-accion" type="submit">Guardar cambios</button>
                </form>
            </main>
        </div>
    </div>
</body>
</html>