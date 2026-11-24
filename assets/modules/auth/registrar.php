<?php
require_once __DIR__ . '/../../includes/auth.php';

if (is_authenticated()) {
    redirect_to('/assets/modules/dashboard/index.php');
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../personas/helpers.php';

$documentTypes = personas_tipos_documento_activos($pdo);
$activeDocumentTypeIds = array_map('intval', array_column($documentTypes, 'tipo_documento_id'));
$clientRoleStatement = $pdo->prepare(
    'SELECT rol_id FROM roles WHERE nombre = :nombre AND activo = 1 LIMIT 1'
);
$clientRoleStatement->execute(['nombre' => 'Cliente']);
$clientRoleId = $clientRoleStatement->fetchColumn();
$registrationReady = $documentTypes !== [] && $clientRoleId !== false;
$errorMessage = '';
$registration = [
    'tipo_documento_id' => '',
    'numero_documento' => '',
    'nombres' => '',
    'apellidos' => '',
    'telefono' => '',
    'fecha_nacimiento' => '',
    'nombre_usuario' => '',
    'email' => '',
];

if (!$registrationReady) {
    $errorMessage = 'El registro no está disponible. Contacta al hotel para completar la configuración.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $registrationReady) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        exit('Solicitud inválida. Vuelve a cargar el formulario e inténtalo de nuevo.');
    }

    $invalidInput = false;
    foreach ($registration as $field => $value) {
        $fieldInput = $_POST[$field] ?? '';
        if (!is_string($fieldInput)) {
            $invalidInput = true;
            $fieldInput = '';
        }
        $registration[$field] = trim($fieldInput);
    }
    $passwordInput = $_POST['password'] ?? '';
    $confirmPasswordInput = $_POST['confirmar_password'] ?? '';
    if (!is_string($passwordInput) || !is_string($confirmPasswordInput)) {
        $invalidInput = true;
        $passwordInput = '';
        $confirmPasswordInput = '';
    }

    $documentTypeId = filter_var(
        $registration['tipo_documento_id'],
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    $passwordLength = strlen($passwordInput);
    if ($invalidInput || $documentTypeId === false
        || !in_array((int) $documentTypeId, $activeDocumentTypeIds, true)
        || $registration['numero_documento'] === '' || strlen($registration['numero_documento']) > 25
        || $registration['nombres'] === '' || strlen($registration['nombres']) > 80
        || $registration['apellidos'] === '' || strlen($registration['apellidos']) > 80
        || strlen($registration['telefono']) > 20
        || !personas_fecha_valida($registration['fecha_nacimiento'])
        || ($registration['fecha_nacimiento'] !== '' && $registration['fecha_nacimiento'] > date('Y-m-d'))
        || !filter_var($registration['email'], FILTER_VALIDATE_EMAIL) || strlen($registration['email']) > 100
        || $registration['nombre_usuario'] === '' || strlen($registration['nombre_usuario']) > 50
        || $passwordLength < 12 || $passwordLength > 72
        || !hash_equals($passwordInput, $confirmPasswordInput)) {
        $errorMessage = 'Verifica tus datos y confirma una contraseña de entre 12 y 72 caracteres.';
    } else {
        try {
            $pdo->beginTransaction();
            $lockedRoleStatement = $pdo->prepare(
                'SELECT rol_id FROM roles
                 WHERE nombre = :nombre AND activo = 1
                 LIMIT 1 FOR UPDATE'
            );
            $lockedRoleStatement->execute(['nombre' => 'Cliente']);
            $lockedClientRoleId = $lockedRoleStatement->fetchColumn();
            $lockedDocumentTypeStatement = $pdo->prepare(
                'SELECT tipo_documento_id
                 FROM tipos_documentos
                 WHERE tipo_documento_id = :tipo_documento_id AND activo = 1
                 LIMIT 1 FOR UPDATE'
            );
            $lockedDocumentTypeStatement->execute(['tipo_documento_id' => $documentTypeId]);
            $lockedDocumentTypeId = $lockedDocumentTypeStatement->fetchColumn();

            if (!$lockedClientRoleId || !$lockedDocumentTypeId) {
                $pdo->rollBack();
                $errorMessage = 'El tipo de documento o el rol de cliente dejó de estar disponible. Recarga el formulario.';
            } else {
                $personStatement = $pdo->prepare(
                    'INSERT INTO personas (
                        tipo_documento_id, numero_documento, nombres, apellidos,
                        telefono, email, fecha_nacimiento
                     ) VALUES (
                        :tipo_documento_id, :numero_documento, :nombres, :apellidos,
                        :telefono, :email, :fecha_nacimiento
                     )'
                );
                $personStatement->execute([
                    'tipo_documento_id' => $documentTypeId,
                    'numero_documento' => $registration['numero_documento'],
                    'nombres' => $registration['nombres'],
                    'apellidos' => $registration['apellidos'],
                    'telefono' => $registration['telefono'] !== '' ? $registration['telefono'] : null,
                    'email' => $registration['email'],
                    'fecha_nacimiento' => $registration['fecha_nacimiento'] !== ''
                        ? $registration['fecha_nacimiento']
                        : null,
                ]);
                $personId = (int) $pdo->lastInsertId();

                $clientStatement = $pdo->prepare(
                    'INSERT INTO clientes (persona_id) VALUES (:persona_id)'
                );
                $clientStatement->execute(['persona_id' => $personId]);

                $userStatement = $pdo->prepare(
                    'INSERT INTO usuarios (
                        persona_id, nombre_usuario, email, password_hash, activo
                     ) VALUES (
                        :persona_id, :nombre_usuario, :email, :password_hash, 1
                     )'
                );
                $userStatement->execute([
                    'persona_id' => $personId,
                    'nombre_usuario' => $registration['nombre_usuario'],
                    'email' => $registration['email'],
                    'password_hash' => password_hash($passwordInput, PASSWORD_DEFAULT),
                ]);
                $userId = (int) $pdo->lastInsertId();

                $userRoleStatement = $pdo->prepare(
                    'INSERT INTO usuario_rol (usuario_id, rol_id)
                     VALUES (:usuario_id, :rol_id)'
                );
                $userRoleStatement->execute([
                    'usuario_id' => $userId,
                    'rol_id' => $lockedClientRoleId,
                ]);
                $pdo->commit();

                session_regenerate_id(true);
                $_SESSION['usuario_id'] = $userId;
                $_SESSION['nombre_usuario'] = $registration['nombre_usuario'];
                unset($_SESSION['csrf_token']);
                redirect_to('/assets/modules/reservas/mis_reservas.php');
            }
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('Hotel Manager customer registration failed: ' . $exception->getMessage());
            $errorMessage = 'No se pudo completar el registro. Revisa que el documento, usuario y correo no estén registrados.';
        }
    }
}

$baseUrl = htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/estilos.css">
    <?php require __DIR__ . '/../../includes/favicon.php'; ?>
    <title>Registro de cliente | Hotel Premium</title>
</head>
<body>
    <main class="principal">
        <header class="cabecera">
            <img src="<?= $baseUrl ?>/assets/img/banners2.fw.png" alt="Hotel Premium" class="banner">
            <img src="<?= $baseUrl ?>/assets/img/logo.png" alt="Logo de Hotel Premium" class="logo">
        </header>
        <div class="cuerpo">
            <h1 class="titulo">Regístrate en Hotel Premium</h1>
            <p class="texto">Crea tu cuenta de cliente para solicitar reservas de habitaciones.</p>
        </div>
        <footer class="pie-pagina">
            <span>&copy; <a href="">EL DIARIO DE UN PROGRAMADOR</a></span>
            <span>HOTEL PREMIUM</span>
        </footer>
    </main>
    <section class="formulario">
        <form class="formulario-registro" method="post">
            <h2 class="formulario-titulo">Crear cuenta de cliente</h2>
            <?php if ($errorMessage !== ''): ?>
                <p class="mensaje-error" role="alert"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">

            <label for="tipo_documento_id">Tipo de documento</label>
            <select id="tipo_documento_id" name="tipo_documento_id" class="input-texto" required <?= $registrationReady ? '' : 'disabled' ?>>
                <option value="">Selecciona un tipo</option>
                <?php foreach ($documentTypes as $documentType): ?>
                    <option
                        value="<?= (int) $documentType['tipo_documento_id'] ?>"
                        <?= (string) $documentType['tipo_documento_id'] === $registration['tipo_documento_id'] ? 'selected' : '' ?>
                    ><?= htmlspecialchars($documentType['nombre'] . ' (' . $documentType['abreviatura'] . ')', ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>

            <label for="numero_documento">Número de documento</label>
            <input id="numero_documento" name="numero_documento" class="input-texto" maxlength="25" required value="<?= htmlspecialchars($registration['numero_documento'], ENT_QUOTES, 'UTF-8') ?>">

            <label for="nombres">Nombres</label>
            <input id="nombres" name="nombres" class="input-texto" maxlength="80" required autocomplete="given-name" value="<?= htmlspecialchars($registration['nombres'], ENT_QUOTES, 'UTF-8') ?>">

            <label for="apellidos">Apellidos</label>
            <input id="apellidos" name="apellidos" class="input-texto" maxlength="80" required autocomplete="family-name" value="<?= htmlspecialchars($registration['apellidos'], ENT_QUOTES, 'UTF-8') ?>">

            <label for="telefono">Teléfono (opcional)</label>
            <input id="telefono" name="telefono" type="tel" class="input-texto" maxlength="20" autocomplete="tel" value="<?= htmlspecialchars($registration['telefono'], ENT_QUOTES, 'UTF-8') ?>">

            <label for="fecha_nacimiento">Fecha de nacimiento (opcional)</label>
            <input id="fecha_nacimiento" name="fecha_nacimiento" type="date" class="input-texto" value="<?= htmlspecialchars($registration['fecha_nacimiento'], ENT_QUOTES, 'UTF-8') ?>">

            <label for="nombre_usuario">Nombre de usuario</label>
            <input id="nombre_usuario" name="nombre_usuario" class="input-texto" maxlength="50" required autocomplete="username" value="<?= htmlspecialchars($registration['nombre_usuario'], ENT_QUOTES, 'UTF-8') ?>">

            <label for="email">Correo electrónico</label>
            <input id="email" name="email" type="email" class="input-texto" maxlength="100" required autocomplete="email" value="<?= htmlspecialchars($registration['email'], ENT_QUOTES, 'UTF-8') ?>">

            <label for="password">Contraseña (12 a 72 caracteres)</label>
            <input id="password" name="password" type="password" class="input-password" minlength="12" maxlength="72" required autocomplete="new-password">

            <label for="confirmar_password">Confirmar contraseña</label>
            <input id="confirmar_password" name="confirmar_password" type="password" class="input-password" minlength="12" maxlength="72" required autocomplete="new-password">

            <button type="submit" class="input-submit" <?= $registrationReady ? '' : 'disabled' ?>>Crear cuenta y reservar</button>
            <a class="perdiste" href="<?= $baseUrl ?>/index.php">¿Ya tienes una cuenta? Inicia sesión</a>
        </form>
    </section>
</body>
</html>
