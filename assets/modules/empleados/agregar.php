<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/helpers.php';
require_role($pdo, 'Administrador');

$peopleStatement = $pdo->query(
    'SELECT p.persona_id, p.numero_documento, p.nombres, p.apellidos, td.abreviatura
     FROM personas p
     INNER JOIN tipos_documentos td ON td.tipo_documento_id = p.tipo_documento_id
     WHERE NOT EXISTS (
         SELECT 1 FROM empleados e WHERE e.persona_id = p.persona_id
     )
     ORDER BY p.apellidos, p.nombres'
);
$availablePeople = $peopleStatement->fetchAll();
$availablePersonIds = array_map('intval', array_column($availablePeople, 'persona_id'));
$employee = ['persona_id' => '', 'cargo' => '', 'fecha_contratacion' => '', 'salario' => ''];
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Solicitud inválida. Vuelve a cargar el formulario e inténtalo de nuevo.');
    }

    $submittedFields = [
        'persona_id' => $_POST['persona_id'] ?? '',
        'cargo' => $_POST['cargo'] ?? '',
        'fecha_contratacion' => $_POST['fecha_contratacion'] ?? '',
        'salario' => $_POST['salario'] ?? '',
    ];
    $validFieldTypes = true;
    foreach ($submittedFields as $field => $value) {
        if (!is_string($value)) {
            $validFieldTypes = false;
            $employee[$field] = '';
        } else {
            $employee[$field] = trim($value);
        }
    }
    $personId = filter_var(
        $employee['persona_id'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if (!$validFieldTypes || $personId === false
        || !in_array((int) $personId, $availablePersonIds, true)
        || $employee['cargo'] === '' || strlen($employee['cargo']) > 80
        || !empleados_fecha_valida($employee['fecha_contratacion'])
        || !empleados_salario_valido($employee['salario'])) {
        $errorMessage = 'Verifica la persona, el cargo (máximo 80 caracteres), la fecha y el salario.';
    } else {
        try {
            $insertStatement = $pdo->prepare(
                'INSERT INTO empleados (persona_id, cargo, fecha_contratacion, salario)
                 VALUES (:persona_id, :cargo, :fecha_contratacion, :salario)'
            );
            $insertStatement->execute([
                'persona_id' => $personId,
                'cargo' => $employee['cargo'],
                'fecha_contratacion' => $employee['fecha_contratacion'],
                'salario' => $employee['salario'] !== '' ? $employee['salario'] : null,
            ]);

            $_SESSION['empleados_mensaje'] = 'El empleado se agregó correctamente.';
            redirect_to('/assets/modules/empleados/index.php');
        } catch (PDOException $exception) {
            error_log('Hotel Manager employee creation failed: ' . $exception->getMessage());
            $errorMessage = 'No se pudo agregar el empleado. Verifica que la persona no tenga ya un registro de empleado.';
        }
    }
}

$baseUrl = htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8');
$tituloCabecera = 'Agregar empleado';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/../../includes/favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/estilos.css">
    <title>Agregar empleado | Hotel Premium</title>
</head>
<body class="panel">
    <div class="panel-panel">
        <?php require __DIR__ . '/../../includes/panel_cabecera.php'; ?>
        <div class="panel-distribucion">
            <?php require __DIR__ . '/../../includes/sidebar.php'; ?>
            <main class="panel-contenido pagina-empleados">
                <header class="empleados-cabecera">
                    <p>Asigna una persona existente como empleado.</p>
                    <a class="boton-secundario" href="<?= $baseUrl ?>/assets/modules/empleados/index.php">Volver a empleados</a>
                </header>

                <?php if ($errorMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if ($availablePeople === []): ?>
                    <p class="mensaje-info">
                        No hay personas disponibles para registrar como empleados.
                        <a href="<?= $baseUrl ?>/assets/modules/personas/agregar.php">Registra una persona</a> primero.
                    </p>
                <?php endif; ?>

                <form class="formulario-empleado" method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

                    <label for="persona_id">Persona</label>
                    <select id="persona_id" name="persona_id" required <?= $availablePeople === [] ? 'disabled' : '' ?>>
                        <option value="">Selecciona una persona</option>
                        <?php foreach ($availablePeople as $person): ?>
                            <option
                                value="<?= (int) $person['persona_id'] ?>"
                                <?= (string) $person['persona_id'] === $employee['persona_id'] ? 'selected' : '' ?>
                            ><?= htmlspecialchars($person['nombres'] . ' ' . $person['apellidos'] . ' — ' . $person['abreviatura'] . ' ' . $person['numero_documento'], ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>

                    <label for="cargo">Cargo</label>
                    <input id="cargo" name="cargo" type="text" maxlength="80" required value="<?= htmlspecialchars($employee['cargo'], ENT_QUOTES, 'UTF-8') ?>">

                    <label for="fecha_contratacion">Fecha de contratación</label>
                    <input id="fecha_contratacion" name="fecha_contratacion" type="date" required value="<?= htmlspecialchars($employee['fecha_contratacion'], ENT_QUOTES, 'UTF-8') ?>">

                    <label for="salario">Salario (opcional)</label>
                    <input id="salario" name="salario" type="number" min="0" max="9999999999.99" step="0.01" value="<?= htmlspecialchars($employee['salario'], ENT_QUOTES, 'UTF-8') ?>">

                    <button class="boton-accion" type="submit" <?= $availablePeople === [] ? 'disabled' : '' ?>>Guardar empleado</button>
                </form>
            </main>
        </div>
    </div>
</body>
</html>