<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';
require_role($pdo, 'Administrador');

$peopleStatement = $pdo->query(
    'SELECT p.persona_id, p.numero_documento, p.nombres, p.apellidos, td.abreviatura
     FROM personas p
     INNER JOIN tipos_documentos td ON td.tipo_documento_id = p.tipo_documento_id
     WHERE NOT EXISTS (
         SELECT 1 FROM clientes c WHERE c.persona_id = p.persona_id
     )
     ORDER BY p.apellidos, p.nombres'
);
$availablePeople = $peopleStatement->fetchAll();
$availablePersonIds = array_map('intval', array_column($availablePeople, 'persona_id'));
$client = ['persona_id' => '', 'observaciones' => ''];
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Solicitud inválida. Vuelve a cargar el formulario e inténtalo de nuevo.');
    }

    $personIdInput = $_POST['persona_id'] ?? '';
    $observationsInput = $_POST['observaciones'] ?? '';
    $validFieldTypes = is_string($personIdInput) && is_string($observationsInput);
    $client['persona_id'] = is_string($personIdInput) ? trim($personIdInput) : '';
    $client['observaciones'] = is_string($observationsInput) ? trim($observationsInput) : '';
    $personId = filter_var(
        $client['persona_id'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if (!$validFieldTypes || $personId === false
        || !in_array((int) $personId, $availablePersonIds, true)
        || strlen($client['observaciones']) > 255) {
        $errorMessage = 'Selecciona una persona que todavía no sea cliente y limita las observaciones a 255 caracteres.';
    } else {
        try {
            $insertStatement = $pdo->prepare(
                'INSERT INTO clientes (persona_id, observaciones)
                 VALUES (:persona_id, :observaciones)'
            );
            $insertStatement->execute([
                'persona_id' => $personId,
                'observaciones' => $client['observaciones'] !== '' ? $client['observaciones'] : null,
            ]);

            $_SESSION['clientes_mensaje'] = 'El cliente se agregó correctamente.';
            redirect_to('/assets/modules/clientes/index.php');
        } catch (PDOException $exception) {
            error_log('Hotel Manager client creation failed: ' . $exception->getMessage());
            $errorMessage = 'No se pudo agregar el cliente. Verifica que la persona no esté registrada como cliente.';
        }
    }
}

$baseUrl = htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8');
$tituloCabecera = 'Agregar cliente';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/../../includes/favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/estilos.css">
    <title>Agregar cliente | Hotel Premium</title>
</head>
<body class="panel">
    <div class="panel-panel">
        <?php require __DIR__ . '/../../includes/panel_cabecera.php'; ?>
        <div class="panel-distribucion">
            <?php require __DIR__ . '/../../includes/sidebar.php'; ?>
            <main class="panel-contenido pagina-clientes">
                <header class="clientes-cabecera">
                    <p>Vincula una persona existente como cliente.</p>
                    <a class="boton-secundario" href="<?= $baseUrl ?>/assets/modules/clientes/index.php">Volver a clientes</a>
                </header>

                <?php if ($errorMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if ($availablePeople === []): ?>
                    <p class="mensaje-info">
                        No hay personas disponibles para registrar como clientes.
                        <a href="<?= $baseUrl ?>/assets/modules/personas/agregar.php">Registra una persona</a> primero.
                    </p>
                <?php endif; ?>

                <form class="formulario-cliente" method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                    <label for="persona_id">Persona</label>
                    <select id="persona_id" name="persona_id" required <?= $availablePeople === [] ? 'disabled' : '' ?>>
                        <option value="">Selecciona una persona</option>
                        <?php foreach ($availablePeople as $person): ?>
                            <option
                                value="<?= (int) $person['persona_id'] ?>"
                                <?= (string) $person['persona_id'] === $client['persona_id'] ? 'selected' : '' ?>
                            ><?= htmlspecialchars($person['nombres'] . ' ' . $person['apellidos'] . ' — ' . $person['abreviatura'] . ' ' . $person['numero_documento'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="observaciones">Observaciones</label>
                    <textarea id="observaciones" name="observaciones" maxlength="255" rows="4"><?= htmlspecialchars($client['observaciones'], ENT_QUOTES, 'UTF-8') ?></textarea>

                    <button class="boton-accion" type="submit" <?= $availablePeople === [] ? 'disabled' : '' ?>>Guardar cliente</button>
                </form>
            </main>
        </div>
    </div>
</body>
</html>