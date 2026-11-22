<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';
require_role($pdo, 'Administrador');

$clientIdInput = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['cliente_id'] ?? '')
    : ($_GET['id'] ?? '');
$clientId = is_string($clientIdInput)
    ? filter_var($clientIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
if ($clientId === false) {
    http_response_code(400);
    exit('ID de cliente inválido.');
}

$clientStatement = $pdo->prepare(
    'SELECT c.cliente_id, c.observaciones, p.nombres, p.apellidos,
            p.numero_documento, td.abreviatura
     FROM clientes c
     INNER JOIN personas p ON p.persona_id = c.persona_id
     INNER JOIN tipos_documentos td ON td.tipo_documento_id = p.tipo_documento_id
     WHERE c.cliente_id = :cliente_id
     LIMIT 1'
);
$clientStatement->execute(['cliente_id' => $clientId]);
$clientRecord = $clientStatement->fetch();
if (!$clientRecord) {
    http_response_code(404);
    exit('No se encontró el cliente solicitado.');
}

$observations = $clientRecord['observaciones'] ?? '';
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Solicitud inválida. Vuelve a cargar el formulario e inténtalo de nuevo.');
    }

    $observationsInput = $_POST['observaciones'] ?? '';
    if (!is_string($observationsInput)) {
        $errorMessage = 'Las observaciones enviadas no son válidas.';
    } else {
        $observations = trim($observationsInput);
        if (strlen($observations) > 255) {
            $errorMessage = 'Las observaciones no pueden superar los 255 caracteres.';
        } else {
            try {
                $updateStatement = $pdo->prepare(
                    'UPDATE clientes
                     SET observaciones = :observaciones
                     WHERE cliente_id = :cliente_id'
                );
                $updateStatement->execute([
                    'observaciones' => $observations !== '' ? $observations : null,
                    'cliente_id' => $clientId,
                ]);

                $_SESSION['clientes_mensaje'] = 'Los cambios del cliente se guardaron correctamente.';
                redirect_to('/assets/modules/clientes/index.php');
            } catch (PDOException $exception) {
                error_log('Hotel Manager client update failed: ' . $exception->getMessage());
                $errorMessage = 'No se pudieron guardar los cambios del cliente.';
            }
        }
    }
}

$baseUrl = htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8');
$tituloCabecera = 'Editar cliente';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/../../includes/favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/estilos.css">
    <title>Editar cliente | Hotel Premium</title>
</head>
<body class="panel">
    <div class="panel-panel">
        <?php require __DIR__ . '/../../includes/panel_cabecera.php'; ?>
        <div class="panel-distribucion">
            <?php require __DIR__ . '/../../includes/sidebar.php'; ?>
            <main class="panel-contenido pagina-clientes">
                <header class="clientes-cabecera">
                    <p>Actualiza las observaciones del cliente.</p>
                    <a class="boton-secundario" href="<?= $baseUrl ?>/assets/modules/clientes/index.php">Volver a clientes</a>
                </header>

                <?php if ($errorMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <form class="formulario-cliente" method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="cliente_id" value="<?= (int) $clientId ?>">

                    <label for="persona">Persona</label>
                    <input
                        id="persona"
                        type="text"
                        value="<?= htmlspecialchars($clientRecord['nombres'] . ' ' . $clientRecord['apellidos'] . ' — ' . $clientRecord['abreviatura'] . ' ' . $clientRecord['numero_documento'], ENT_QUOTES, 'UTF-8') ?>"
                        disabled
                    >
                    <p class="ayuda-formulario">Los datos personales se actualizan desde el módulo Personas.</p>

                    <label for="observaciones">Observaciones</label>
                    <textarea id="observaciones" name="observaciones" maxlength="255" rows="4"><?= htmlspecialchars($observations, ENT_QUOTES, 'UTF-8') ?></textarea>

                    <button class="boton-accion" type="submit">Guardar cambios</button>
                </form>
            </main>
        </div>
    </div>
</body>
</html>