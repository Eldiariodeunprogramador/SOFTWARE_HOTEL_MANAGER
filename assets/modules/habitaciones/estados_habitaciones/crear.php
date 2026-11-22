<?php
require_once __DIR__ . '/../../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../../config/database.php';
require_role($pdo, 'Administrador');

$name = '';
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
                $insertStatement = $pdo->prepare(
                    'INSERT INTO estados_habitaciones (nombre) VALUES (:nombre)'
                );
                $insertStatement->execute(['nombre' => $name]);

                $_SESSION['estados_habitaciones_mensaje'] = 'El estado de habitación se creó correctamente.';
                redirect_to('/assets/modules/habitaciones/estados_habitaciones/index.php');
            } catch (PDOException $exception) {
                error_log('Hotel Manager room state creation failed: ' . $exception->getMessage());
                $errorMessage = 'No se pudo crear el estado. Verifica que el nombre no esté duplicado.';
            }
        }
    }
}

$baseUrl = htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8');
$tituloCabecera = 'Crear estado de habitación';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/../../../includes/favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/estilos.css">
    <title>Crear estado de habitación | Hotel Premium</title>
</head>
<body class="panel">
    <div class="panel-panel">
        <?php require __DIR__ . '/../../../includes/panel_cabecera.php'; ?>
        <div class="panel-distribucion">
            <?php require __DIR__ . '/../../../includes/sidebar.php'; ?>
            <main class="panel-contenido pagina-habitaciones">
                <header class="habitaciones-cabecera">
                    <p>Registra un nuevo estado para las habitaciones.</p>
                    <a class="boton-secundario" href="<?= $baseUrl ?>/assets/modules/habitaciones/estados_habitaciones/index.php">Volver a estados</a>
                </header>

                <?php if ($errorMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <form class="formulario-habitacion" method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                    <label for="nombre">Nombre</label>
                    <input id="nombre" name="nombre" type="text" maxlength="50" required value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>">

                    <button class="boton-accion" type="submit">Crear estado</button>
                </form>
            </main>
        </div>
    </div>
</body>
</html>