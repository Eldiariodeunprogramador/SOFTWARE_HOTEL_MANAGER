<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';
require_role($pdo, 'Administrador');

$types = $pdo->query(
    'SELECT tipo_habitacion_id, nombre, capacidad, precio_noche
     FROM tipos_habitaciones
     WHERE activo = 1
     ORDER BY nombre'
)->fetchAll();
$states = $pdo->query(
    'SELECT estado_habitacion_id, nombre
     FROM estados_habitaciones
     ORDER BY nombre'
)->fetchAll();
$typeIds = array_map('intval', array_column($types, 'tipo_habitacion_id'));
$stateIds = array_map('intval', array_column($states, 'estado_habitacion_id'));
$room = [
    'numero' => '',
    'tipo_habitacion_id' => '',
    'estado_habitacion_id' => '',
    'piso' => '',
    'descripcion' => '',
];
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Solicitud inválida. Vuelve a cargar el formulario e inténtalo de nuevo.');
    }

    $validFieldTypes = true;
    foreach ($room as $field => $value) {
        $fieldValue = $_POST[$field] ?? '';
        if (!is_string($fieldValue)) {
            $validFieldTypes = false;
            $fieldValue = '';
        }
        $room[$field] = trim($fieldValue);
    }
    $typeId = filter_var($room['tipo_habitacion_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $stateId = filter_var($room['estado_habitacion_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $floor = $room['piso'] === ''
        ? null
        : filter_var($room['piso'], FILTER_VALIDATE_INT, ['options' => ['min_range' => -2147483648, 'max_range' => 2147483647]]);

    if (!$validFieldTypes || $room['numero'] === '' || strlen($room['numero']) > 10
        || $typeId === false || !in_array((int) $typeId, $typeIds, true)
        || $stateId === false || !in_array((int) $stateId, $stateIds, true)
        || ($room['piso'] !== '' && $floor === false)
        || strlen($room['descripcion']) > 200) {
        $errorMessage = 'Verifica el número (máximo 10 caracteres), tipo, estado, piso y descripción (máximo 200 caracteres).';
    } else {
        try {
            $insertStatement = $pdo->prepare(
                'INSERT INTO habitaciones (numero, tipo_habitacion_id, estado_habitacion_id, piso, descripcion)
                 VALUES (:numero, :tipo_habitacion_id, :estado_habitacion_id, :piso, :descripcion)'
            );
            $insertStatement->execute([
                'numero' => $room['numero'],
                'tipo_habitacion_id' => $typeId,
                'estado_habitacion_id' => $stateId,
                'piso' => $floor,
                'descripcion' => $room['descripcion'] !== '' ? $room['descripcion'] : null,
            ]);

            $_SESSION['habitaciones_mensaje'] = 'La habitación se creó correctamente.';
            redirect_to('/assets/modules/habitaciones/index.php');
        } catch (PDOException $exception) {
            error_log('Hotel Manager room creation failed: ' . $exception->getMessage());
            $errorMessage = 'No se pudo crear la habitación. Verifica que el número no esté registrado y que el tipo y el estado sigan disponibles.';
        }
    }
}

$baseUrl = htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8');
$tituloCabecera = 'Crear habitación';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/../../includes/favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/estilos.css">
    <title>Crear habitación | Hotel Premium</title>
</head>
<body class="panel">
    <div class="panel-panel">
        <?php require __DIR__ . '/../../includes/panel_cabecera.php'; ?>
        <div class="panel-distribucion">
            <?php require __DIR__ . '/../../includes/sidebar.php'; ?>
            <main class="panel-contenido pagina-habitaciones">
                <header class="habitaciones-cabecera">
                    <p>Completa la información de la habitación.</p>
                    <a class="boton-secundario" href="<?= $baseUrl ?>/assets/modules/habitaciones/index.php">Volver a habitaciones</a>
                </header>

                <?php if ($errorMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if ($types === []): ?>
                    <p class="mensaje-info">No hay tipos de habitación activos. Activa un tipo antes de crear habitaciones.</p>
                <?php endif; ?>
                <?php if ($states === []): ?>
                    <p class="mensaje-registro mensaje-error">No hay estados de habitación disponibles. Verifica la configuración del sistema.</p>
                <?php endif; ?>

                <form class="formulario-habitacion" method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                    <label for="numero">Número de habitación</label>
                    <input id="numero" name="numero" type="text" maxlength="10" required value="<?= htmlspecialchars($room['numero'], ENT_QUOTES, 'UTF-8') ?>">

                    <label for="tipo_habitacion_id">Tipo de habitación</label>
                    <select id="tipo_habitacion_id" name="tipo_habitacion_id" required <?= $types === [] ? 'disabled' : '' ?>>
                        <option value="">Selecciona un tipo</option>
                        <?php foreach ($types as $type): ?>
                            <option
                                value="<?= (int) $type['tipo_habitacion_id'] ?>"
                                <?= (string) $type['tipo_habitacion_id'] === $room['tipo_habitacion_id'] ? 'selected' : '' ?>
                            ><?= htmlspecialchars($type['nombre'] . ' — capacidad ' . $type['capacidad'] . ', precio ' . $type['precio_noche'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="estado_habitacion_id">Estado</label>
                    <select id="estado_habitacion_id" name="estado_habitacion_id" required <?= $states === [] ? 'disabled' : '' ?>>
                        <option value="">Selecciona un estado</option>
                        <?php foreach ($states as $state): ?>
                            <option
                                value="<?= (int) $state['estado_habitacion_id'] ?>"
                                <?= (string) $state['estado_habitacion_id'] === $room['estado_habitacion_id'] ? 'selected' : '' ?>
                            ><?= htmlspecialchars($state['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="piso">Piso (opcional)</label>
                    <input id="piso" name="piso" type="number" step="1" min="-2147483648" max="2147483647" value="<?= htmlspecialchars($room['piso'], ENT_QUOTES, 'UTF-8') ?>">

                    <label for="descripcion">Descripción (opcional)</label>
                    <textarea id="descripcion" name="descripcion" maxlength="200" rows="4"><?= htmlspecialchars($room['descripcion'], ENT_QUOTES, 'UTF-8') ?></textarea>

                    <button class="boton-accion" type="submit" <?= $types === [] || $states === [] ? 'disabled' : '' ?>>Crear habitación</button>
                </form>
            </main>
        </div>
    </div>
</body>
</html>