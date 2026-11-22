<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';
require_role($pdo, 'Administrador');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Solicitud inválida.');
}

$personId = filter_var(
    $_POST['persona_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
if ($personId === false) {
    http_response_code(400);
    exit('ID de persona inválido.');
}

try {
    $pdo->beginTransaction();
    $personStatement = $pdo->prepare(
        'SELECT persona_id FROM personas WHERE persona_id = :persona_id FOR UPDATE'
    );
    $personStatement->execute(['persona_id' => $personId]);
    if (!$personStatement->fetchColumn()) {
        $pdo->rollBack();
        $_SESSION['personas_error'] = 'La persona no existe o ya fue eliminada.';
        redirect_to('/assets/modules/personas/index.php');
    }

    $referencesStatement = $pdo->prepare(
        'SELECT
            EXISTS (SELECT 1 FROM usuarios WHERE persona_id = ?) OR
            EXISTS (SELECT 1 FROM clientes WHERE persona_id = ?) OR
            EXISTS (SELECT 1 FROM empleados WHERE persona_id = ?) AS tiene_referencias'
    );
    $referencesStatement->execute([$personId, $personId, $personId]);
    if ((bool) $referencesStatement->fetchColumn()) {
        $pdo->rollBack();
        $_SESSION['personas_error'] = 'No se puede eliminar esta persona porque está vinculada a un usuario, cliente o empleado.';
        redirect_to('/assets/modules/personas/index.php');
    }

    $deleteStatement = $pdo->prepare(
        'DELETE FROM personas WHERE persona_id = :persona_id'
    );
    $deleteStatement->execute(['persona_id' => $personId]);
    $pdo->commit();
    $_SESSION['personas_mensaje'] = 'La persona se eliminó correctamente.';
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Hotel Manager person deletion failed: ' . $exception->getMessage());
    $_SESSION['personas_error'] = 'No se pudo eliminar la persona. Puede tener registros relacionados; verifica sus asociaciones e inténtalo de nuevo.';
}

redirect_to('/assets/modules/personas/index.php');