<?php
function personas_tipos_documento_activos(PDO $pdo): array
{
    return $pdo->query(
        'SELECT tipo_documento_id, nombre, abreviatura
         FROM tipos_documentos
         WHERE activo = 1
         ORDER BY nombre'
    )->fetchAll();
}

function personas_fecha_valida(string $date): bool
{
    if ($date === '') {
        return true;
    }
    if (strlen($date) !== 10 || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        return false;
    }

    $parsedDate = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    $errors = DateTimeImmutable::getLastErrors();

    return $parsedDate !== false
        && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
        && $parsedDate->format('Y-m-d') === $date;
}
