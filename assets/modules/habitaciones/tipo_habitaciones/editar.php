<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../config/database.php';
require_role($pdo, 'Administrador');

$roomTypeIdInput = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['tipo_habitacion_id'] ?? '')
    : ($_GET['id'] ?? '');
$roomTypeId = filter_var(
    $roomTypeIdInput,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
if ($roomTypeId === false) {
    http_response_code(400);
    exit('ID de tipo de habitación inválido.');
}

$roomTypeStatement = $pdo->prepare(
    'SELECT tipo_habitacion_id, nombre, capacidad, precio_noche, activo
     FROM tipos_habitaciones
     WHERE tipo_habitacion_id = :tipo_habitacion_id
     LIMIT 1'
);
$roomTypeStatement->execute(['tipo_habitacion_id' => $roomTypeId]);
$roomType = $roomTypeStatement->fetch();
if (!$roomType) {
    http_response_code(404);
    exit('No se encontró el tipo de habitación solicitado.');
}

$name = $roomType['nombre'];
$capacityInput = (string) $roomType['capacidad'];
$priceInput = (string) $roomType['precio_noche'];
$active = (bool) $roomType['activo'];
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Solicitud inválida. Vuelve a cargar el formulario e inténtalo de nuevo.');
    }

    $nameInput = $_POST['nombre'] ?? '';
    $capacityValue = $_POST['capacidad'] ?? '';
    $priceValue = $_POST['precio_noche'] ?? '';
    $activeValue = $_POST['activo'] ?? '';
    if (!is_string($nameInput) || !is_string($capacityValue)
        || !is_string($priceValue) || !is_string($activeValue)) {
        $errorMessage = 'Los datos enviados no son válidos.';
    } else {
        $name = trim($nameInput);
        $capacityInput = trim($capacityValue);
        $priceInput = trim($priceValue);
        $active = $activeValue === '1';
        $capacity = filter_var(
            $capacityInput,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1, 'max_range' => 2147483647]]
        );
        $validPrice = preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $priceInput) === 1;

        if ($name === '' || strlen($name) > 50 || $capacity === false || !$validPrice
            || !in_array($activeValue, ['0', '1'], true)) {
            $errorMessage = 'Verifica que el nombre tenga hasta 50 caracteres, la capacidad sea un entero positivo y el precio tenga hasta 8 enteros y 2 decimales.';
        } else {
            try {
                $updateStatement = $pdo->prepare(
                    'UPDATE tipos_habitaciones
                     SET nombre = :nombre, capacidad = :capacidad,
                         precio_noche = :precio_noche, activo = :activo
                     WHERE tipo_habitacion_id = :tipo_habitacion_id'
                );
                $updateStatement->execute([
                    'nombre' => $name,
                    'capacidad' => $capacity,
                    'precio_noche' => $priceInput,
                    'activo' => $active ? 1 : 0,
                    'tipo_habitacion_id' => $roomTypeId,
                ]);

                $_SESSION['tipos_habitaciones_mensaje'] = 'Los cambios del tipo de habitación se guardaron correctamente.';
                redirect_to('/assets/modules/habitaciones/tipo_habitaciones/index.php');
            } catch (PDOException $exception) {
                error_log('Hotel Manager room type update failed: ' . $exception->getMessage());
                $errorMessage = 'No se pudieron guardar los cambios. Verifica que el nombre no esté duplicado.';
            }
        }
    }
}

$baseUrl = htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8');
$tituloCabecera = 'Editar tipo de habitación';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/../../../includes/favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/estilos.css">
    <title>Editar tipo de habitación | Hotel Premium</title>
</head>
<body class="panel">
    <div class="panel-panel">
        <?php require __DIR__ . '/../../../includes/panel_cabecera.php'; ?>
        <div class="panel-distribucion">
            <?php require __DIR__ . '/../../../includes/sidebar.php'; ?>
            <main class="panel-contenido pagina-habitaciones">
                <header class="habitaciones-cabecera">
                    <p>Actualiza los datos y el estado del tipo de habitación.</p>
                    <a class="boton-secundario" href="<?= $baseUrl ?>/assets/modules/habitaciones/tipo_habitaciones/index.php">Volver a tipos</a>
                </header>

                <?php if ($errorMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <form class="formulario-habitacion" method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="tipo_habitacion_id" value="<?= (int) $roomTypeId ?>">

                    <label for="nombre">Nombre</label>
                    <input id="nombre" name="nombre" type="text" maxlength="50" required value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>">

                    <label for="capacidad">Capacidad (personas)</label>
                    <input id="capacidad" name="capacidad" type="number" min="1" max="2147483647" step="1" required value="<?= htmlspecialchars($capacityInput, ENT_QUOTES, 'UTF-8') ?>">

                    <label for="precio_noche">Precio por noche</label>
                    <input id="precio_noche" name="precio_noche" type="number" min="0" max="99999999.99" step="0.01" required value="<?= htmlspecialchars($priceInput, ENT_QUOTES, 'UTF-8') ?>">

                    <label for="activo">Estado</label>
                    <select id="activo" name="activo" required>
                        <option value="1" <?= $active ? 'selected' : '' ?>>Activo</option>
                        <option value="0" <?= !$active ? 'selected' : '' ?>>Inactivo</option>
                    </select>

                    <button class="boton-accion" type="submit">Guardar cambios</button>
                </form>
            </main>
        </div>
    </div>
</body>
</html>