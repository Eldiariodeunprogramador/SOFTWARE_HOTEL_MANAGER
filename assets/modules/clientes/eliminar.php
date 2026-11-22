<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';
require_role($pdo, 'Administrador');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Solicitud inválida.');
}

$clientIdInput = $_POST['cliente_id'] ?? null;
$clientId = is_string($clientIdInput)
    ? filter_var($clientIdInput, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
    : false;
if ($clientId === false) {
    http_response_code(400);
    exit('ID de cliente inválido.');
}

try {
    $pdo->beginTransaction();
    $clientStatement = $pdo->prepare(
        'SELECT cliente_id FROM clientes WHERE cliente_id = :cliente_id FOR UPDATE'
    );
    $clientStatement->execute(['cliente_id' => $clientId]);
    if (!$clientStatement->fetchColumn()) {
        $pdo->rollBack();
        $_SESSION['clientes_error'] = 'El cliente no existe o ya fue eliminado.';
        redirect_to('/assets/modules/clientes/index.php');
    }

    $reservationsStatement = $pdo->prepare(
        'SELECT COUNT(*) FROM reservas WHERE cliente_id = :cliente_id'
    );
    $reservationsStatement->execute(['cliente_id' => $clientId]);
    if ((int) $reservationsStatement->fetchColumn() > 0) {
        $pdo->rollBack();
        $_SESSION['clientes_error'] = 'No se puede eliminar este cliente porque tiene reservas registradas.';
        redirect_to('/assets/modules/clientes/index.php');
    }

    $deleteStatement = $pdo->prepare(
        'DELETE FROM clientes WHERE cliente_id = :cliente_id'
    );
    $deleteStatement->execute(['cliente_id' => $clientId]);
    $pdo->commit();
    $_SESSION['clientes_mensaje'] = 'El cliente se eliminó correctamente.';
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Hotel Manager client deletion failed: ' . $exception->getMessage());
    $_SESSION['clientes_error'] = 'No se pudo eliminar el cliente. Verifica que no tenga registros relacionados.';
}

redirect_to('/assets/modules/clientes/index.php');