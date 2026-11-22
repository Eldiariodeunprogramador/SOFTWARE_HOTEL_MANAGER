<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../config/database.php';
require_role($pdo, 'Administrador');

$roomType = [
    'nombre' => '',
    'capacidad' => '',
    'precio_noche' => '',
];
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Solicitud inválida. Vuelve a cargar el formulario e inténtalo de nuevo.');
    }

    $validFieldTypes = true;
    foreach ($roomType as $field => $value) {
        $fieldValue = $_POST[$field] ?? '';
        if (!is_string($fieldValue)) {
            $validFieldTypes = false;
            $fieldValue = '';
        }
        $roomType[$field] = trim($fieldValue);
    }
    $capacity = filter_var(
        $roomType['capacidad'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 2147483647]]
    );
    $validPrice = preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $roomType['precio_noche']) === 1;

    if (!$validFieldTypes || $roomType['nombre'] === '' || strlen($roomType['nombre']) > 50
        || $capacity === false || !$validPrice) {
        $errorMessage = 'Verifica que el nombre tenga hasta 50 caracteres, la capacidad sea un entero positivo y el precio tenga hasta 8 enteros y 2 decimales.';
    } else {
        try {
            $insertStatement = $pdo->prepare(
                'INSERT INTO tipos_habitaciones (nombre, capacidad, precio_noche, activo)
                 VALUES (:nombre, :capacidad, :precio_noche, 1)'
            );
            $insertStatement->execute([
                'nombre' => $roomType['nombre'],
                'capacidad' => $capacity,
                'precio_noche' => $roomType['precio_noche'],
            ]);

            $_SESSION['tipos_habitaciones_mensaje'] = 'El tipo de habitación se creó correctamente.';
            redirect_to('/assets/modules/habitaciones/tipo_habitaciones/index.php');
        } catch (PDOException $exception) {
            error_log('Hotel Manager room type creation failed: ' . $exception->getMessage());
            $errorMessage = 'No se pudo crear el tipo de habitación. Verifica que el nombre no esté registrado.';
        }
    }
}

$baseUrl = htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8');
$tituloCabecera = 'Crear tipo de habitación';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/../../../includes/favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/estilos.css">
    <title>Crear tipo de habitación | Hotel Premium</title>
</head>
<body class="panel">
    <div class="panel-panel">
        <?php require __DIR__ . '/../../../includes/panel_cabecera.php'; ?>
        <div class="panel-distribucion">
            <?php require __DIR__ . '/../../../includes/sidebar.php'; ?>
            <main class="panel-contenido pagina-habitaciones">
                <header class="habitaciones-cabecera">
                    <p>Registra la capacidad y tarifa por noche del tipo de habitación.</p>
                    <a class="boton-secundario" href="<?= $baseUrl ?>/assets/modules/habitaciones/tipo_habitaciones/index.php">Volver a tipos</a>
                </header>

                <?php if ($errorMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <form class="formulario-habitacion" method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                    <label for="nombre">Nombre</label>
                    <input id="nombre" name="nombre" type="text" maxlength="50" required value="<?= htmlspecialchars($roomType['nombre'], ENT_QUOTES, 'UTF-8') ?>">

                    <label for="capacidad">Capacidad (personas)</label>
                    <input id="capacidad" name="capacidad" type="number" min="1" max="2147483647" step="1" required value="<?= htmlspecialchars($roomType['capacidad'], ENT_QUOTES, 'UTF-8') ?>">

                    <label for="precio_noche">Precio por noche</label>
                    <input id="precio_noche" name="precio_noche" type="number" min="0" max="99999999.99" step="0.01" required value="<?= htmlspecialchars($roomType['precio_noche'], ENT_QUOTES, 'UTF-8') ?>">

                    <button class="boton-accion" type="submit">Crear tipo</button>
                </form>
            </main>
        </div>
    </div>
</body>
</html>