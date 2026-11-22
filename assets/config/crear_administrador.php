<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if ($argc !== 4) {
    fwrite(
        STDERR,
        "Uso: php assets/config/crear_administrador.php <persona_id> <nombre_usuario> <email>\n"
    );
    exit(1);
}

$personaId = filter_var($argv[1], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$username = trim($argv[2]);
$email = trim($argv[3]);

if ($personaId === false || $username === '' || strlen($username) > 50
    || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) {
    fwrite(STDERR, "Verifica persona_id, nombre de usuario (máximo 50 caracteres) y correo.\n");
    exit(1);
}

require_once __DIR__ . '/database.php';

$permissionCount = (int) $pdo->query('SELECT COUNT(*) FROM permisos WHERE activo = 1')->fetchColumn();
if ($permissionCount === 0) {
    fwrite(STDERR, "No hay permisos activos. Ejecuta primero assets/config/rol_permiso.sql.\n");
    exit(1);
}

$password = bin2hex(random_bytes(18));
$passwordHash = password_hash($password, PASSWORD_DEFAULT);

try {
    $pdo->beginTransaction();

    $roleStatement = $pdo->prepare('SELECT rol_id, activo FROM roles WHERE nombre = :nombre LIMIT 1');
    $roleStatement->execute(['nombre' => 'Administrador']);
    $role = $roleStatement->fetch();

    if ($role && !(bool) $role['activo']) {
        $pdo->rollBack();
        fwrite(STDERR, "El rol Administrador existe pero está inactivo. Actívalo antes de continuar.\n");
        exit(1);
    }

    if ($role) {
        $missingPermissionsStatement = $pdo->prepare(
            'SELECT COUNT(*)
             FROM permisos p
             LEFT JOIN rol_permiso rp
                 ON rp.permiso_id = p.permiso_id AND rp.rol_id = :rol_id
             WHERE p.activo = 1 AND rp.permiso_id IS NULL'
        );
        $missingPermissionsStatement->execute(['rol_id' => $role['rol_id']]);
        if ((int) $missingPermissionsStatement->fetchColumn() > 0) {
            $pdo->rollBack();
            fwrite(STDERR, "El rol Administrador ya existe y no tiene todos los permisos; no se modificó para evitar ampliar el acceso de otros usuarios.\n");
            exit(1);
        }
        $roleId = (int) $role['rol_id'];
    } else {
        $roleInsertStatement = $pdo->prepare(
            "INSERT INTO roles (nombre, descripcion, activo)
             VALUES ('Administrador', 'Acceso administrativo completo', 1)"
        );
        $roleInsertStatement->execute();
        $roleId = (int) $pdo->lastInsertId();

        $permissionsStatement = $pdo->prepare(
            'INSERT INTO rol_permiso (rol_id, permiso_id)
             SELECT :rol_id, permiso_id FROM permisos WHERE activo = 1'
        );
        $permissionsStatement->execute(['rol_id' => $roleId]);
    }

    $userStatement = $pdo->prepare(
        'INSERT INTO usuarios (persona_id, nombre_usuario, email, password_hash, activo)
         VALUES (:persona_id, :nombre_usuario, :email, :password_hash, 1)'
    );
    $userStatement->execute([
        'persona_id' => $personaId,
        'nombre_usuario' => $username,
        'email' => $email,
        'password_hash' => $passwordHash,
    ]);
    $userId = (int) $pdo->lastInsertId();

    $userRoleStatement = $pdo->prepare(
        'INSERT INTO usuario_rol (usuario_id, rol_id) VALUES (:usuario_id, :rol_id)'
    );
    $userRoleStatement->execute(['usuario_id' => $userId, 'rol_id' => $roleId]);

    $pdo->commit();
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('Hotel Manager administrator setup failed: ' . $exception->getMessage());
    fwrite(STDERR, "No se pudo crear el administrador. Verifica persona_id, usuario/correo únicos y las tablas.\n");
    exit(1);
}

fwrite(STDOUT, "Administrador creado correctamente.\n");
fwrite(STDOUT, "Usuario: {$username}\n");
fwrite(STDOUT, "Contraseña temporal (guárdala ahora): {$password}\n");
