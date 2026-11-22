<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/helpers.php';
require_role($pdo, 'Administrador');

$employeeIdInput = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['empleado_id'] ?? '')
    : ($_GET['id'] ?? '');
$employeeId = is_string($employeeIdInput)
    ? filter_var($employeeIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
if ($employeeId === false) {
    http_response_code(400);
    exit('ID de empleado inválido.');
}

$employeeStatement = $pdo->prepare(
    'SELECT e.empleado_id, e.cargo, e.fecha_contratacion, e.salario, e.activo,
            p.nombres, p.apellidos, p.numero_documento, td.abreviatura
     FROM empleados e
     INNER JOIN personas p ON p.persona_id = e.persona_id
     INNER JOIN tipos_documentos td ON td.tipo_documento_id = p.tipo_documento_id
     WHERE e.empleado_id = :empleado_id
     LIMIT 1'
);
$employeeStatement->execute(['empleado_id' => $employeeId]);
$employeeRecord = $employeeStatement->fetch();
if (!$employeeRecord) {
    http_response_code(404);
    exit('No se encontró el empleado solicitado.');
}

$employee = [
    'cargo' => $employeeRecord['cargo'],
    'fecha_contratacion' => $employeeRecord['fecha_contratacion'],
    'salario' => $employeeRecord['salario'] ?? '',
    'activo' => (string) (int) $employeeRecord['activo'],
];
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Solicitud inválida. Vuelve a cargar el formulario e inténtalo de nuevo.');
    }

    $submittedFields = [
        'cargo' => $_POST['cargo'] ?? '',
        'fecha_contratacion' => $_POST['fecha_contratacion'] ?? '',
        'salario' => $_POST['salario'] ?? '',
        'activo' => $_POST['activo'] ?? '',
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

    if (!$validFieldTypes || $employee['cargo'] === '' || strlen($employee['cargo']) > 80
        || !empleados_fecha_valida($employee['fecha_contratacion'])
        || !empleados_salario_valido($employee['salario'])
        || !in_array($employee['activo'], ['0', '1'], true)) {
        $errorMessage = 'Verifica el cargo (máximo 80 caracteres), la fecha, el salario y el estado del empleado.';
    } else {
        try {
            $updateStatement = $pdo->prepare(
                'UPDATE empleados
                 SET cargo = :cargo,
                     fecha_contratacion = :fecha_contratacion,
                     salario = :salario,
                     activo = :activo
                 WHERE empleado_id = :empleado_id'
            );
            $updateStatement->execute([
                'cargo' => $employee['cargo'],
                'fecha_contratacion' => $employee['fecha_contratacion'],
                'salario' => $employee['salario'] !== '' ? $employee['salario'] : null,
                'activo' => (int) $employee['activo'],
                'empleado_id' => $employeeId,
            ]);

            $_SESSION['empleados_mensaje'] = 'Los cambios del empleado se guardaron correctamente.';
            redirect_to('/assets/modules/empleados/index.php');
        } catch (PDOException $exception) {
            error_log('Hotel Manager employee update failed: ' . $exception->getMessage());
            $errorMessage = 'No se pudieron guardar los cambios del empleado.';
        }
    }
}

$baseUrl = htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8');
$tituloCabecera = 'Editar empleado';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/../../includes/favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/estilos.css">
    <title>Editar empleado | Hotel Premium</title>
</head>
<body class="panel">
    <div class="panel-panel">
        <?php require __DIR__ . '/../../includes/panel_cabecera.php'; ?>
        <div class="panel-distribucion">
            <?php require __DIR__ . '/../../includes/sidebar.php'; ?>
            <main class="panel-contenido pagina-empleados">
                <header class="empleados-cabecera">
                    <p>Actualiza los datos laborales del empleado.</p>
                    <a class="boton-secundario" href="<?= $baseUrl ?>/assets/modules/empleados/index.php">Volver a empleados</a>
                </header>

                <?php if ($errorMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <form class="formulario-empleado" method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="empleado_id" value="<?= (int) $employeeId ?>">

                    <label for="persona">Persona</label>
                    <input
                        id="persona"
                        type="text"
                        value="<?= htmlspecialchars($employeeRecord['nombres'] . ' ' . $employeeRecord['apellidos'] . ' — ' . $employeeRecord['abreviatura'] . ' ' . $employeeRecord['numero_documento'], ENT_QUOTES, 'UTF-8') ?>"
                        disabled
                    >
                    <p class="ayuda-formulario">Los datos personales se actualizan desde el módulo Personas.</p>

                    <label for="cargo">Cargo</label>
                    <input id="cargo" name="cargo" type="text" maxlength="80" required value="<?= htmlspecialchars($employee['cargo'], ENT_QUOTES, 'UTF-8') ?>">

                    <label for="fecha_contratacion">Fecha de contratación</label>
                    <input id="fecha_contratacion" name="fecha_contratacion" type="date" required value="<?= htmlspecialchars($employee['fecha_contratacion'], ENT_QUOTES, 'UTF-8') ?>">

                    <label for="salario">Salario (opcional)</label>
                    <input id="salario" name="salario" type="number" min="0" max="9999999999.99" step="0.01" value="<?= htmlspecialchars((string) $employee['salario'], ENT_QUOTES, 'UTF-8') ?>">

                    <label for="activo">Estado</label>
                    <select id="activo" name="activo" required>
                        <option value="1" <?= $employee['activo'] === '1' ? 'selected' : '' ?>>Activo</option>
                        <option value="0" <?= $employee['activo'] === '0' ? 'selected' : '' ?>>Inactivo</option>
                    </select>

                    <button class="boton-accion" type="submit">Guardar cambios</button>
                </form>
            </main>
        </div>
    </div>
</body>
</html>