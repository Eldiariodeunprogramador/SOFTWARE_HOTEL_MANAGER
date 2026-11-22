<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/helpers.php';
require_role($pdo, 'Administrador');

$documentTypes = personas_tipos_documento_activos($pdo);
$activeDocumentTypeIds = array_map('intval', array_column($documentTypes, 'tipo_documento_id'));
$errorMessage = '';
$person = [
    'tipo_documento_id' => '',
    'numero_documento' => '',
    'nombres' => '',
    'apellidos' => '',
    'telefono' => '',
    'email' => '',
    'direccion' => '',
    'fecha_nacimiento' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Solicitud inválida. Vuelve a cargar el formulario e inténtalo de nuevo.');
    }

    $invalidFieldInput = false;
    foreach ($person as $field => $value) {
        $fieldValue = $_POST[$field] ?? '';
        if (!is_string($fieldValue)) {
            $invalidFieldInput = true;
            $fieldValue = '';
        }
        $person[$field] = trim($fieldValue);
    }
    $documentTypeId = filter_var(
        $person['tipo_documento_id'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($invalidFieldInput || $documentTypeId === false || !in_array((int) $documentTypeId, $activeDocumentTypeIds, true)
        || $person['numero_documento'] === '' || strlen($person['numero_documento']) > 25
        || $person['nombres'] === '' || strlen($person['nombres']) > 80
        || $person['apellidos'] === '' || strlen($person['apellidos']) > 80
        || strlen($person['telefono']) > 20
        || ($person['email'] !== '' && (!filter_var($person['email'], FILTER_VALIDATE_EMAIL) || strlen($person['email']) > 100))
        || strlen($person['direccion']) > 150
        || !personas_fecha_valida($person['fecha_nacimiento'])) {
        $errorMessage = 'Revisa el tipo y número de documento, los nombres, apellidos, teléfono, correo, dirección y fecha de nacimiento.';
    } else {
        try {
            $insertStatement = $pdo->prepare(
                'INSERT INTO personas (
                    tipo_documento_id, numero_documento, nombres, apellidos,
                    telefono, email, direccion, fecha_nacimiento
                 ) VALUES (
                    :tipo_documento_id, :numero_documento, :nombres, :apellidos,
                    :telefono, :email, :direccion, :fecha_nacimiento
                 )'
            );
            $insertStatement->execute([
                'tipo_documento_id' => $documentTypeId,
                'numero_documento' => $person['numero_documento'],
                'nombres' => $person['nombres'],
                'apellidos' => $person['apellidos'],
                'telefono' => $person['telefono'] !== '' ? $person['telefono'] : null,
                'email' => $person['email'] !== '' ? $person['email'] : null,
                'direccion' => $person['direccion'] !== '' ? $person['direccion'] : null,
                'fecha_nacimiento' => $person['fecha_nacimiento'] !== '' ? $person['fecha_nacimiento'] : null,
            ]);

            $_SESSION['personas_mensaje'] = 'La persona se agregó correctamente.';
            redirect_to('/assets/modules/personas/index.php');
        } catch (PDOException $exception) {
            error_log('Hotel Manager person creation failed: ' . $exception->getMessage());
            $errorMessage = 'No se pudo agregar la persona. Verifica que el documento no esté registrado y que el tipo de documento siga activo.';
        }
    }
}

$baseUrl = htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8');
$tituloCabecera = 'Agregar persona';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/estilos.css">
    <title>Agregar persona | Hotel Premium</title>
</head>
<body class="panel">
    <div class="panel-panel">
        <?php require __DIR__ . '/../../includes/panel_cabecera.php'; ?>
        <div class="panel-distribucion">
            <?php require __DIR__ . '/../../includes/sidebar.php'; ?>
            <main class="panel-contenido pagina-personas">
                <header class="personas-cabecera">
                    <p>Completa los datos de identificación y contacto.</p>
                    <a class="boton-secundario" href="<?= $baseUrl ?>/assets/modules/personas/index.php">Volver a personas</a>
                </header>

                <?php if ($documentTypes === []): ?>
                    <p class="mensaje-registro mensaje-error">No hay tipos de documento activos. Activa uno antes de registrar personas.</p>
                <?php endif; ?>
                <?php if ($errorMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <form class="formulario-persona" method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                    <label for="tipo_documento_id">Tipo de documento</label>
                    <select id="tipo_documento_id" name="tipo_documento_id" required>
                        <option value="">Selecciona un tipo</option>
                        <?php foreach ($documentTypes as $documentType): ?>
                            <option
                                value="<?= (int) $documentType['tipo_documento_id'] ?>"
                                <?= (string) $documentType['tipo_documento_id'] === $person['tipo_documento_id'] ? 'selected' : '' ?>
                            ><?= htmlspecialchars($documentType['nombre'] . ' (' . $documentType['abreviatura'] . ')', ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="numero_documento">Número de documento</label>
                    <input id="numero_documento" name="numero_documento" type="text" maxlength="25" required value="<?= htmlspecialchars($person['numero_documento'], ENT_QUOTES, 'UTF-8') ?>">

                    <label for="nombres">Nombres</label>
                    <input id="nombres" name="nombres" type="text" maxlength="80" required value="<?= htmlspecialchars($person['nombres'], ENT_QUOTES, 'UTF-8') ?>">

                    <label for="apellidos">Apellidos</label>
                    <input id="apellidos" name="apellidos" type="text" maxlength="80" required value="<?= htmlspecialchars($person['apellidos'], ENT_QUOTES, 'UTF-8') ?>">

                    <label for="telefono">Teléfono</label>
                    <input id="telefono" name="telefono" type="tel" maxlength="20" value="<?= htmlspecialchars($person['telefono'], ENT_QUOTES, 'UTF-8') ?>">

                    <label for="email">Correo electrónico</label>
                    <input id="email" name="email" type="email" maxlength="100" value="<?= htmlspecialchars($person['email'], ENT_QUOTES, 'UTF-8') ?>">

                    <label for="direccion">Dirección</label>
                    <input id="direccion" name="direccion" type="text" maxlength="150" value="<?= htmlspecialchars($person['direccion'], ENT_QUOTES, 'UTF-8') ?>">

                    <label for="fecha_nacimiento">Fecha de nacimiento</label>
                    <input id="fecha_nacimiento" name="fecha_nacimiento" type="date" value="<?= htmlspecialchars($person['fecha_nacimiento'], ENT_QUOTES, 'UTF-8') ?>">

                    <button class="boton-accion" type="submit">Guardar persona</button>
                </form>
            </main>
        </div>
    </div>
</body>
</html>