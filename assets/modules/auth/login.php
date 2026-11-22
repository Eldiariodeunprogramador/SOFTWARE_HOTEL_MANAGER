<?php
require_once __DIR__ . '/../../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verify_csrf_token($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Solicitud inválida. Vuelve a cargar el inicio de sesión e inténtalo de nuevo.');
}

$identifier = trim((string) ($_POST['identificador'] ?? ''));
$password = (string) ($_POST['password'] ?? '');
if ($identifier === '' || $password === '') {
    $_SESSION['login_error'] = 'Ingresa tu usuario y contraseña.';
    redirect_to('/index.php');
}

require_once __DIR__ . '/../../config/database.php';

$statement = $pdo->prepare(
    'SELECT usuario_id, nombre_usuario, password_hash
     FROM usuarios
     WHERE activo = 1 AND (nombre_usuario = :usuario OR email = :email)
     LIMIT 1'
);
$statement->execute(['usuario' => $identifier, 'email' => $identifier]);
$user = $statement->fetch();

if (!$user || !password_verify($password, $user['password_hash'])) {
    $_SESSION['login_error'] = 'Usuario o contraseña incorrectos.';
    redirect_to('/index.php');
}

$roleStatement = $pdo->prepare(
    'SELECT 1
     FROM usuario_rol ur
     INNER JOIN roles r ON r.rol_id = ur.rol_id AND r.activo = 1
     WHERE ur.usuario_id = :usuario_id
     LIMIT 1'
);
$roleStatement->execute(['usuario_id' => $user['usuario_id']]);
if (!$roleStatement->fetchColumn()) {
    $_SESSION['login_error'] = 'Tu usuario no tiene un rol activo asignado.';
    redirect_to('/index.php');
}

session_regenerate_id(true);
$_SESSION['usuario_id'] = (int) $user['usuario_id'];
$_SESSION['nombre_usuario'] = $user['nombre_usuario'];
unset($_SESSION['login_error'], $_SESSION['csrf_token']);

redirect_to('/assets/modules/dashboard/index.php');