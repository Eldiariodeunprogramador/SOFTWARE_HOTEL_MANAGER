<header class="panel-cabecera">
    <img
        class="panel-banner"
        src="<?= htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/img/banners3.fw.png"
        alt=""
        aria-hidden="true"
    >
    <div class="panel-cabecera-contenido">
        <h1><?= htmlspecialchars($tituloCabecera, ENT_QUOTES, 'UTF-8') ?></h1>
        <p>Sesión iniciada como <?= htmlspecialchars($_SESSION['nombre_usuario'], ENT_QUOTES, 'UTF-8') ?></p>
    </div>
    <form action="<?= htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8') ?>/assets/modules/auth/logout.php" method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
        <button class="boton-salir" type="submit">Cerrar sesión</button>
    </form>
</header>
