<?php
require_once __DIR__ . '/../../includes/auth.php';
require_login();
require_once __DIR__ . '/../../config/database.php';
require_role($pdo, 'Administrador');

$statement = $pdo->query(
    'SELECT td.tipo_documento_id, td.nombre, td.abreviatura,
            td.descripcion, td.activo, COUNT(p.persona_id) AS personas_asociadas
     FROM tipos_documentos td
     LEFT JOIN personas p ON p.tipo_documento_id = td.tipo_documento_id
     GROUP BY td.tipo_documento_id, td.nombre, td.abreviatura, td.descripcion, td.activo
     ORDER BY td.nombre'
);
$documentTypes = $statement->fetchAll();
$activeTypesCount = (int) $pdo->query(
    'SELECT COUNT(*) FROM tipos_documentos WHERE activo = 1'
)->fetchColumn();
$successMessage = $_SESSION['tipos_documentos_mensaje'] ?? '';
$errorMessage = $_SESSION['tipos_documentos_error'] ?? '';
unset($_SESSION['tipos_documentos_mensaje'], $_SESSION['tipos_documentos_error']);
$baseUrl = htmlspecialchars(HOTEL_BASE_URL, ENT_QUOTES, 'UTF-8');
$tituloCabecera = 'Tipos de documentos';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/estilos.css">
    <title>Tipos de documentos | Hotel Premium</title>
</head>
<body class="panel">
    <div class="panel-panel">
        <?php require __DIR__ . '/../../includes/panel_cabecera.php'; ?>
        <div class="panel-distribucion">
            <?php require __DIR__ . '/../../includes/sidebar.php'; ?>
            <main class="panel-contenido pagina-tipos-documentos">
                <header class="tipos-documentos-cabecera">
                    <p>Administra los documentos permitidos para registrar personas.</p>
                    <a class="boton-accion" href="<?= $baseUrl ?>/assets/modules/tipos_documentos/agregar.php">
                        <i class="fa-solid fa-plus" aria-hidden="true"></i>
                        Agregar tipo
                    </a>
                </header>

                <?php if ($successMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-exito"><?= htmlspecialchars($successMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <?php if ($errorMessage !== ''): ?>
                    <p class="mensaje-registro mensaje-error"><?= htmlspecialchars($errorMessage, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <?php if ($documentTypes === []): ?>
                    <p class="mensaje-info">No hay tipos de documentos registrados.</p>
                <?php else: ?>
                    <div class="tabla-contenedor">
                        <table class="tabla-registros">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th>Abreviatura</th>
                                    <th>Descripción</th>
                                    <th>Personas asociadas</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($documentTypes as $documentType): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($documentType['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($documentType['abreviatura'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($documentType['descripcion'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= (int) $documentType['personas_asociadas'] ?></td>
                                        <td>
                                            <span class="estado-usuario <?= (bool) $documentType['activo'] ? 'estado-activo' : 'estado-inactivo' ?>">
                                                <?= (bool) $documentType['activo'] ? 'Activo' : 'Inactivo' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="acciones-registro">
                                                <a
                                                    class="boton-secundario"
                                                    href="<?= $baseUrl ?>/assets/modules/tipos_documentos/editar.php?id=<?= (int) $documentType['tipo_documento_id'] ?>"
                                                >Editar</a>
                                                <?php if ((bool) $documentType['activo'] && $activeTypesCount > 1): ?>
                                                    <form
                                                        action="<?= $baseUrl ?>/assets/modules/tipos_documentos/eliminar.php"
                                                        method="post"
                                                        onsubmit="return confirm('¿Deseas desactivar este tipo de documento?');"
                                                    >
                                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>">
                                                        <input type="hidden" name="tipo_documento_id" value="<?= (int) $documentType['tipo_documento_id'] ?>">
                                                        <button class="boton-peligro" type="submit">Desactivar</button>
                                                    </form>
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