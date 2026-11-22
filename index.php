<?php
require_once __DIR__ . '/assets/includes/auth.php';

if (is_authenticated()) {
    redirect_to('/assets/modules/dashboard/index.php');
}

$loginError = $_SESSION['login_error'] ?? '';
unset($_SESSION['login_error']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/estilos.css">
    <link rel="shortcut icon" href="assets/img/logo.png" type="image/x-icon">
    <title>Inicio de sesión | Hotel Premium</title>
</head>
<body>
    <main class="principal">
        <header class="cabecera">
            <img src="assets/img/banners2.fw.png" alt="Hotel Premium" class="banner">
            <img src="assets/img/logo.png" alt="Logo de Hotel Premium" class="logo">
        </header>
        <div class="cuerpo">
            <h1 class="titulo">BIENVENIDOS HOTEL PREMIUM SOFTWARE V 1.0</h1>
            <ul class="servicios">
                <li><i class="fa-solid fa-bed" aria-hidden="true"></i>Reserva de habitaciones</li>
                <li><i class="fa-solid fa-user-plus" aria-hidden="true"></i>Registro de huéspedes</li>
                <li><i class="fa-solid fa-right-left" aria-hidden="true"></i>Check-in y check-out</li>
                <li><i class="fa-solid fa-door-open" aria-hidden="true"></i>Control de habitaciones</li>
                <li><i class="fa-solid fa-file-invoice-dollar" aria-hidden="true"></i>Facturación y pago</li>
            </ul>
            <p class="texto">Para solicitar más información acerca de este software, comuníquese al número de celular 310 490 54 17 o al correo electrónico eldiariodeunprogramador@gmail.com.</p>
        </div>
        <footer class="pie-pagina">
            <span>&copy; <a href="">EL DIARIO DE UN PROGRAMADOR</a></span>
            <span>TODOS LOS DERECHOS RESERVADOR | LEONEL OSPINA RESTREPO</span>
        </footer>
    </main>
    <section class="formulario">
        <form action="assets/modules/auth/login.php" method="post">
            <h3 class="formulario-titulo">BIENVENIDOS</h3>
            <p class="formulario-subtitulo"><i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i> INICIO DE SESIÓN</p>
            <?php if ($loginError !== ''): ?>
                <p class="mensaje-error" role="alert"><?= htmlspecialchars($loginError, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
            <label class="sr-only" for="identificador">Usuario o correo electrónico</label>
            <input type="text" name="identificador" id="identificador" class="input-texto" placeholder="Usuario o correo electrónico" autocomplete="username" required>
            <label class="sr-only" for="password">Contraseña</label>
            <input type="password" name="password" id="password" class="input-password" placeholder="Contraseña" autocomplete="current-password" required>
            <input type="submit" value="INGRESAR" class="input-submit">
        </form>
    </section>
</body>
</html>