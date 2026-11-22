<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';
require_role($pdo, 'Administrador');

$statement = $pdo->query(
    'SELECT p.persona_id, p.numero_documento, p.nombres, p.apellidos,
            p.telefono, p.email, p.fecha_nacimiento, td.abreviatura
     FROM personas p
     INNER JOIN tipos_documentos td ON td.tipo_documento_id = p.tipo_documento_id
     ORDER BY p.apellidos, p.nombres'
);
$personas = $statement->fetchAll();
$successMessage = $_SESSION['personas_mensaje'] ?? '';
$errorMessage = $_SESSION['personas_error'] ?? '';
unset($_SESSION['personas_mensaje'], $_SESSION['personas_error']);
$baseUrl = htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8');
$tituloCabecera = 'Personas';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/../../includes/favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/estilos.css">
    <title>Personas | Hotel Premium</title>
</head>
<body class="panel">
    <div class="panel-panel">
        <?php require __DIR__ . '/../../includes/panel_cabecera.php'; ?>
        <div class="panel-distribucion">
            <?php require __DIR__ . '/../../includes/sidebar.php'; ?>
            <main class="panel-contenido pagina-personas">
                <header class="personas-cabecera">
                    <p>Consulta y administra el directorio de personas.</p>
                    <a class="boton-accion" href="<?= $baseUrl ?>/assets/modules/personas/agregar.php">
                        <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                        Agregar persona
                    </a>
                </header>

                <?php if ($successMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-exito"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if ($errorMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <?php if ($personas === []): ?>
                    <p class="mensaje-info">No hay personas registradas.</p>
                <?php else: ?>
                    <div class="tabla-contenedor">
                        <table class="tabla-personas">
                            <thead>
                                <tr>
                                    <th>Nombre completo</th>
                                    <th>Documento</th>
                                    <th>Teléfono</th>
                                    <th>Correo</th>
                                    <th>Fecha de nacimiento</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($personas as $persona): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($persona['nombres'] . ' ' . $persona['apellidos'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <?= htmlspecialchars($persona['abreviatura'] . ' ' . $persona['numero_documento'], ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                        <td><?= htmlspecialchars($persona['telefono'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($persona['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($persona['fecha_nacimiento'] ?? 'No indicada', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <div class="acciones-persona">
                                                <a
                                                    class="boton-secundario"
                                                    href="<?= $baseUrl ?>/assets/modules/personas/editar.php?id=<?= (int) $persona['persona_id'] ?>"
                                                >Editar</a>
                                                <form
                                                    action="<?= $baseUrl ?>/assets/modules/personas/eliminar.php"
                                                    method="post"
                                                    onsubmit="return confirm('¿Deseas eliminar esta persona?');"
                                                >
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="persona_id" value="<?= (int) $persona['persona_id'] ?>">
                                                    <button class="boton-peligro" type="submit">Eliminar</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>