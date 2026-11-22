<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';
require_role($pdo, 'Administrador');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Solicitud inválida.');
}

$documentTypeId = filter_var(
    $_POST['tipo_documento_id'] ?? null,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]
);
if ($documentTypeId === false) {
    http_response_code(400);
    exit('ID de tipo de documento inválido.');
}

try {
    $pdo->beginTransaction();
    $activeTypesStatement = $pdo->query(
        'SELECT tipo_documento_id
         FROM tipos_documentos
         WHERE activo = 1
         FOR UPDATE'
    );
    $activeTypeIds = array_column($activeTypesStatement->fetchAll(), 'tipo_documento_id');
    $documentTypeStatement = $pdo->prepare(
        'SELECT activo
         FROM tipos_documentos
         WHERE tipo_documento_id = :tipo_documento_id
         LIMIT 1'
    );
    $documentTypeStatement->execute(['tipo_documento_id' => $documentTypeId]);
    $documentType = $documentTypeStatement->fetch();
    if (!$documentType) {
        $pdo->rollBack();
        $_SESSION['tipos_documentos_error'] = 'El tipo de documento no existe o ya fue eliminado.';
        redirect_to('/assets/modules/tipos_documentos/index.php');
    }

    if (!(bool) $documentType['activo']) {
        $pdo->rollBack();
        $_SESSION['tipos_documentos_error'] = 'El tipo de documento ya está inactivo.';
        redirect_to('/assets/modules/tipos_documentos/index.php');
    }

    if (count($activeTypeIds) <= 1) {
        $pdo->rollBack();
        $_SESSION['tipos_documentos_error'] = 'Debe permanecer al menos un tipo de documento activo.';
        redirect_to('/assets/modules/tipos_documentos/index.php');
    }

    $deactivateStatement = $pdo->prepare(
        'UPDATE tipos_documentos SET activo = 0 WHERE tipo_documento_id = :tipo_documento_id'
    );
    $deactivateStatement->execute(['tipo_documento_id' => $documentTypeId]);
    $pdo->commit();
    $_SESSION['tipos_documentos_mensaje'] = 'El tipo de documento se desactivó correctamente.';
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Hotel Manager document type deactivation failed: ' . $exception->getMessage());
    $_SESSION['tipos_documentos_error'] = 'No se pudo desactivar el tipo de documento. Inténtalo de nuevo o contacta al administrador del sistema.';
}

redirect_to('/assets/modules/tipos_documentos/index.php');