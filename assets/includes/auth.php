<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    $isHttps = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $isHttps,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!defined('HOTEL_BASE_URL')) {
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $assetsPosition = strpos($scriptName, '/assets/');
    if ($assetsPosition !== false) {
        define('HOTEL_BASE_URL', substr($scriptName, 0, $assetsPosition));
    } else {
        define('HOTEL_BASE_URL', rtrim(str_replace('\\', '/', dirname($scriptName)), '/'));
    }
}

function redirect_to(string $path): void
{
    header('Location: ' . HOTEL_BASE_URL . $path);
    exit;
}

function is_authenticated(): bool
{
    return isset($_SESSION['usuario_id']) && is_int($_SESSION['usuario_id']);
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function require_login(): void
{
    if (!is_authenticated()) {
        redirect_to('/index.php');
    }
}

function user_modules(PDO $pdo): array
{
    $statement = $pdo->prepare(
        'SELECT DISTINCT p.modulo
         FROM usuario_rol ur
         INNER JOIN roles r ON r.rol_id = ur.rol_id AND r.activo = 1
         INNER JOIN rol_permiso rp ON rp.rol_id = r.rol_id
         INNER JOIN permisos p ON p.permiso_id = rp.permiso_id
             AND p.activo = 1 AND p.nombre = CONCAT(p.modulo, \'.ver\')
         WHERE ur.usuario_id = :usuario_id
         ORDER BY p.modulo'
    );
    $statement->execute(['usuario_id' => $_SESSION['usuario_id']]);

    return array_column($statement->fetchAll(), 'modulo');
}

function require_permission(string $permission): void
{
    require_login();

    require_once __DIR__ . '/../config/database.php';
    $statement = $pdo->prepare(
        'SELECT 1
         FROM usuario_rol ur
         INNER JOIN roles r ON r.rol_id = ur.rol_id AND r.activo = 1
         INNER JOIN rol_permiso rp ON rp.rol_id = r.rol_id
         INNER JOIN permisos p ON p.permiso_id = rp.permiso_id AND p.activo = 1
         WHERE ur.usuario_id = :usuario_id AND p.nombre = :permiso
         LIMIT 1'
    );
    $statement->execute([
        'usuario_id' => $_SESSION['usuario_id'],
        'permiso' => $permission,
    ]);

    if (!$statement->fetchColumn()) {
        http_response_code(403);
        echo '<!DOCTYPE html><html lang="es"><meta charset="UTF-8"><title>Acceso denegado</title>'
            . '<body><h1>Acceso denegado</h1><p>Tu rol no tiene permiso para ver este módulo.</p>'
            . '<a href="' . htmlspecialchars(HOTEL_BASE_URL . '/assets/modules/dashboard/index.php', ENT_QUOTES, 'UTF-8')
            . '">Volver al panel</a></body></html>';
        exit;
    }
}
