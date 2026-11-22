<?php
function roles_permisos_activos(PDO $pdo): array
{
    return $pdo->query(
        'SELECT permiso_id, nombre, modulo, descripcion
         FROM permisos
         WHERE activo = 1
         ORDER BY modulo, nombre'
    )->fetchAll();
}

function roles_parsear_permisos($input): ?array
{
    if (!is_array($input)) {
        return null;
    }

    $permissionIds = [];
    foreach ($input as $value) {
        if (!is_int($value) && !is_string($value)) {
            return null;
        }

        $permissionId = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($permissionId === false) {
            return null;
        }

        $permissionIds[] = (int) $permissionId;
    }

    return array_values(array_unique($permissionIds));
}

function roles_permisos_validos(PDO $pdo, array $permissionIds): bool
{
    if ($permissionIds === []) {
        return true;
    }

    $placeholders = implode(', ', array_fill(0, count($permissionIds), '?'));
    $statement = $pdo->prepare(
        "SELECT COUNT(*) FROM permisos WHERE activo = 1 AND permiso_id IN ({$placeholders})"
    );
    $statement->execute($permissionIds);

    return (int) $statement->fetchColumn() === count($permissionIds);
}
