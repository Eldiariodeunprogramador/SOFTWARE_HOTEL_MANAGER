<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';
require_role($pdo, 'Administrador');

$statement = $pdo->query(
    'SELECT c.cliente_id, c.fecha_registro, c.observaciones,
            p.nombres, p.apellidos, p.numero_documento, td.abreviatura,
            COUNT(r.reserva_id) AS reservas
     FROM clientes c
     INNER JOIN personas p ON p.persona_id = c.persona_id
     INNER JOIN tipos_documentos td ON td.tipo_documento_id = p.tipo_documento_id
     LEFT JOIN reservas r ON r.cliente_id = c.cliente_id
     GROUP BY c.cliente_id, c.fecha_registro, c.observaciones,
              p.nombres, p.apellidos, p.numero_documento, td.abreviatura
     ORDER BY p.apellidos, p.nombres'
);
$clients = $statement->fetchAll();
$successMessage = $_SESSION['clientes_mensaje'] ?? '';
$errorMessage = $_SESSION['clientes_error'] ?? '';
unset($_SESSION['clientes_mensaje'], $_SESSION['clientes_error']);
$baseUrl = htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8');
$tituloCabecera = 'Clientes';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php require __DIR__ . '/../../includes/favicon.php'; ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/estilos.css">
    <title>Clientes | Hotel Premium</title>
</head>
<body class="panel">
    <div class="panel-panel">
        <?php require __DIR__ . '/../../includes/panel_cabecera.php'; ?>
        <div class="panel-distribucion">
            <?php require __DIR__ . '/../../includes/sidebar.php'; ?>
            <main class="panel-contenido pagina-clientes">
                <header class="clientes-cabecera">
                    <p>Consulta y administra los clientes registrados.</p>
                    <a class="boton-accion" href="<?= $baseUrl ?>/assets/modules/clientes/agregar.php">
                        <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
                        Agregar cliente
                    </a>
                </header>

                <?php if ($successMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-exito"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if ($errorMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <?php if ($clients === []): ?>
                    <p class="mensaje-info">No hay clientes registrados.</p>
                <?php else: ?>
                    <div class="tabla-contenedor">
                        <table class="tabla-clientes">
                            <thead>
                                <tr>
                                    <th>Nombre completo</th>
                                    <th>Documento</th>
                                    <th>Fecha de registro</th>
                                    <th>Reservas</th>
                                    <th>Observaciones</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($clients as $client): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($client['nombres'] . ' ' . $client['apellidos'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($client['abreviatura'] . ' ' . $client['numero_documento'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($client['fecha_registro'] ?? 'No indicada', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= (int) $client['reservas'] ?></td>
                                        <td><?= htmlspecialchars($client['observaciones'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <div class="acciones-cliente">
                                                <a
                                                    class="boton-secundario"
                                                    href="<?= $baseUrl ?>/assets/modules/clientes/editar.php?id=<?= (int) $client['cliente_id'] ?>"
                                                >Editar</a>
                                                <?php if ((int) $client['reservas'] === 0): ?>
                                                    <form
                                                        action="<?= $baseUrl ?>/assets/modules/clientes/eliminar.php"
                                                        method="post"
                                                        onsubmit="return confirm('¿Deseas eliminar este cliente?');"
                                                    >
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="cliente_id" value="<?= (int) $client['cliente_id'] ?>">
                                                        <button class="boton-peligro" type="submit">Eliminar</button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="ayuda-formulario">Tiene reservas</span>
                                                <?php endif; ?>
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