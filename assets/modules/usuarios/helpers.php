<?php
function usuarios_roles_activos(PDO $pdo): array
{
    return $pdo->query('SELECT rol_id, nombre FROM roles WHERE activo = 1 ORDER BY nombre')->fetchAll();
}

function usuarios_parsear_roles($input): ?array
{
    if (!is_array($input)) {
        return null;
    }

    $roleIds = [];
    foreach ($input as $value) {
        if (!is_int($value) && !is_string($value)) {
            return null;
        }

        $roleId = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($roleId === false) {
            return null;
        }

        $roleIds[] = (int) $roleId;
    }

    return array_values(array_unique($roleIds));
}

function usuarios_roles_validos(PDO $pdo, array $roleIds): bool
{
    if ($roleIds === []) {
        return false;
    }

    $placeholders = implode(', ', array_fill(0, count($roleIds), '?'));
    $statement = $pdo->prepare(
        "SELECT COUNT(*) FROM roles WHERE activo = 1 AND rol_id IN ({$placeholders})"
    );
    $statement->execute($roleIds);

    return (int) $statement->fetchColumn() === count($roleIds);
}

function usuarios_id_rol_administrador(PDO $pdo): ?int
{
    $statement = $pdo->prepare(
        'SELECT rol_id FROM roles WHERE nombre = :nombre AND activo = 1 LIMIT 1'
    );
    $statement->execute(['nombre' => 'Administrador']);
    $roleId = $statement->fetchColumn();

    return $roleId === false ? null : (int) $roleId;
}

