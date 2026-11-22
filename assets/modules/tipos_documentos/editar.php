<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';
require_role($pdo, 'Administrador');

$documentTypeIdInput = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['tipo_documento_id'] ?? '')
    : ($_GET['id'] ?? '');
$documentTypeId = filter_var(
    $documentTypeIdInput,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
if ($documentTypeId === false) {
    http_response_code(400);
    exit('ID de tipo de documento inválido.');
}

$documentTypeStatement = $pdo->prepare(
    'SELECT tipo_documento_id, nombre, abreviatura, descripcion, activo
     FROM tipos_documentos
     WHERE tipo_documento_id = :tipo_documento_id
     LIMIT 1'
);
$documentTypeStatement->execute(['tipo_documento_id' => $documentTypeId]);
$documentType = $documentTypeStatement->fetch();
if (!$documentType) {
    http_response_code(404);
    exit('No se encontró el tipo de documento solicitado.');
}

$name = $documentType['nombre'];
$abbreviation = $documentType['abreviatura'];
$description = $documentType['descripcion'] ?? '';
$active = (bool) $documentType['activo'];
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Solicitud inválida. Vuelve a cargar el formulario e inténtalo de nuevo.');
    }

    $nameInput = $_POST['nombre'] ?? '';
    $abbreviationInput = $_POST['abreviatura'] ?? '';
    $descriptionInput = $_POST['descripcion'] ?? '';
    $activeInput = $_POST['activo'] ?? '';
    if (!is_string($nameInput) || !is_string($abbreviationInput)
        || !is_string($descriptionInput) || !is_string($activeInput)) {
        $errorMessage = 'Los datos enviados no son válidos.';
    } else {
        $name = trim($nameInput);
        $abbreviation = trim($abbreviationInput);
        $description = trim($descriptionInput);
        $active = $activeInput === '1';

        if ($name === '' || strlen($name) > 50
            || $abbreviation === '' || strlen($abbreviation) > 10
            || strlen($description) > 150
            || !in_array($activeInput, ['0', '1'], true)) {
            $errorMessage = 'Verifica que el nombre y la abreviatura estén completos y respeten sus límites; la descripción admite hasta 150 caracteres.';
        } else {
            try {
                $pdo->beginTransaction();
                if ($activeInput === '0' && (bool) $documentType['activo']) {
                    $activeTypesStatement = $pdo->query(
                        'SELECT tipo_documento_id
                         FROM tipos_documentos
                         WHERE activo = 1
                         FOR UPDATE'
                    );
                    if (count($activeTypesStatement->fetchAll()) <= 1) {
                        $pdo->rollBack();
                        $errorMessage = 'Debe permanecer al menos un tipo de documento activo.';
                    }
                }

                if ($errorMessage === '') {
                    $updateStatement = $pdo->prepare(
                        'UPDATE tipos_documentos
                         SET nombre = :nombre, abreviatura = :abreviatura,
                             descripcion = :descripcion, activo = :activo
                         WHERE tipo_documento_id = :tipo_documento_id'
                    );
                    $updateStatement->execute([
                        'nombre' => $name,
                        'abreviatura' => $abbreviation,
                        'descripcion' => $description !== '' ? $description : null,
                        'activo' => $active ? 1 : 0,
                        'tipo_documento_id' => $documentTypeId,
                    ]);

                    $pdo->commit();
                    $_SESSION['tipos_documentos_mensaje'] = 'Los cambios del tipo de documento se guardaron correctamente.';
                    redirect_to('/assets/modules/tipos_documentos/index.php');
                }
            } catch (PDOException $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Hotel Manager document type update failed: ' . $exception->getMessage());
                $errorMessage = 'No se pudieron guardar los cambios. Verifica que el nombre y la abreviatura no estén duplicados.';
            }
        }
    }
}

$baseUrl = htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8');
$tituloCabecera = 'Editar tipo de documento';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/../../includes/favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/estilos.css">
    <title>Editar tipo de documento | Hotel Premium</title>
</head>
<body class="panel">
    <div class="panel-panel">
        <?php require __DIR__ . '/../../includes/panel_cabecera.php'; ?>
        <div class="panel-distribucion">
            <?php require __DIR__ . '/../../includes/sidebar.php'; ?>
            <main class="panel-contenido pagina-tipos-documentos">
                <header class="tipos-documentos-cabecera">
                    <p>Actualiza el nombre, la abreviatura y el estado del tipo.</p>
                    <a class="boton-secundario" href="<?= $baseUrl ?>/assets/modules/tipos_documentos/index.php">Volver a tipos</a>
                </header>

                <?php if ($errorMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <form class="formulario-tipo-documento" method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="tipo_documento_id" value="<?= (int) $documentTypeId ?>">

                    <label for="nombre">Nombre</label>
                    <input id="nombre" name="nombre" type="text" maxlength="50" required value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>">

                    <label for="abreviatura">Abreviatura</label>
                    <input id="abreviatura" name="abreviatura" type="text" maxlength="10" required value="<?= htmlspecialchars($abbreviation, ENT_QUOTES, 'UTF-8') ?>">

                    <label for="descripcion">Descripción</label>
                    <textarea id="descripcion" name="descripcion" maxlength="150" rows="3"><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></textarea>

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