<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';
require_role($pdo, 'Administrador');

$documentType = [
    'nombre' => '',
    'abreviatura' => '',
    'descripcion' => '',
];
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Solicitud inválida. Vuelve a cargar el formulario e inténtalo de nuevo.');
    }

    $invalidInput = false;
    foreach ($documentType as $field => $value) {
        $fieldValue = $_POST[$field] ?? '';
        if (!is_string($fieldValue)) {
            $invalidInput = true;
            $fieldValue = '';
        }
        $documentType[$field] = trim($fieldValue);
    }

    if ($invalidInput || $documentType['nombre'] === '' || strlen($documentType['nombre']) > 50
        || $documentType['abreviatura'] === '' || strlen($documentType['abreviatura']) > 10
        || strlen($documentType['descripcion']) > 150) {
        $errorMessage = 'Verifica que el nombre y la abreviatura estén completos y respeten sus límites; la descripción admite hasta 150 caracteres.';
    } else {
        try {
            $insertStatement = $pdo->prepare(
                'INSERT INTO tipos_documentos (nombre, abreviatura, descripcion, activo)
                 VALUES (:nombre, :abreviatura, :descripcion, 1)'
            );
            $insertStatement->execute([
                'nombre' => $documentType['nombre'],
                'abreviatura' => $documentType['abreviatura'],
                'descripcion' => $documentType['descripcion'] !== '' ? $documentType['descripcion'] : null,
            ]);

            $_SESSION['tipos_documentos_mensaje'] = 'El tipo de documento se agregó correctamente.';
            redirect_to('/assets/modules/tipos_documentos/index.php');
        } catch (PDOException $exception) {
            error_log('Hotel Manager document type creation failed: ' . $exception->getMessage());
            $errorMessage = 'No se pudo agregar el tipo. Verifica que el nombre y la abreviatura no estén registrados.';
        }
    }
}

$baseUrl = htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8');
$tituloCabecera = 'Agregar tipo de documento';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/estilos.css">
    <title>Agregar tipo de documento | Hotel Premium</title>
</head>
<body class="panel">
    <div class="panel-panel">
        <?php require __DIR__ . '/../../includes/panel_cabecera.php'; ?>
        <div class="panel-distribucion">
            <?php require __DIR__ . '/../../includes/sidebar.php'; ?>
            <main class="panel-contenido pagina-tipos-documentos">
                <header class="tipos-documentos-cabecera">
                    <p>Registra un tipo de documento para el directorio de personas.</p>
                    <a class="boton-secundario" href="<?= $baseUrl ?>/assets/modules/tipos_documentos/index.php">Volver a tipos</a>
                </header>

                <?php if ($errorMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <form class="formulario-tipo-documento" method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                    <label for="nombre">Nombre</label>
                    <input id="nombre" name="nombre" type="text" maxlength="50" required value="<?= htmlspecialchars($documentType['nombre'], ENT_QUOTES, 'UTF-8') ?>">

                    <label for="abreviatura">Abreviatura</label>
                    <input id="abreviatura" name="abreviatura" type="text" maxlength="10" required value="<?= htmlspecialchars($documentType['abreviatura'], ENT_QUOTES, 'UTF-8') ?>">

                    <label for="descripcion">Descripción</label>
                    <textarea id="descripcion" name="descripcion" maxlength="150" rows="3"><?= htmlspecialchars($documentType['descripcion'], ENT_QUOTES, 'UTF-8') ?></textarea>

                    <button class="boton-accion" type="submit">Guardar tipo</button>
                </form>
            </main>
        </div>
    </div>
</body>
</html>