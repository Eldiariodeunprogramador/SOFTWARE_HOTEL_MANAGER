CREATE TABLE IF NOT EXISTS rol_permiso (
    rol_id INT NOT NULL,
    permiso_id INT NOT NULL,
    PRIMARY KEY (rol_id, permiso_id),
    CONSTRAINT fk_rol_permiso_rol
        FOREIGN KEY (rol_id)
        REFERENCES roles(rol_id),
    CONSTRAINT fk_rol_permiso_permiso
        FOREIGN KEY (permiso_id)
        REFERENCES permisos(permiso_id)
) ENGINE=InnoDB;

INSERT IGNORE INTO permisos (nombre, modulo, descripcion) VALUES
    ('reservas.ver', 'reservas', 'Consultar reservas'),
    ('reservas.crear', 'reservas', 'Crear reservas'),
    ('reservas.editar', 'reservas', 'Editar reservas'),
    ('reservas.cancelar', 'reservas', 'Cancelar reservas'),
    ('habitaciones.ver', 'habitaciones', 'Consultar habitaciones'),
    ('habitaciones.crear', 'habitaciones', 'Crear habitaciones'),
    ('habitaciones.editar', 'habitaciones', 'Editar habitaciones'),
    ('habitaciones.eliminar', 'habitaciones', 'Eliminar habitaciones'),
    ('huespedes.ver', 'huespedes', 'Consultar huéspedes'),
    ('huespedes.crear', 'huespedes', 'Crear huéspedes'),
    ('huespedes.editar', 'huespedes', 'Editar huéspedes'),
    ('pagos.ver', 'pagos', 'Consultar pagos'),
    ('pagos.registrar', 'pagos', 'Registrar pagos de reservas'),
    ('reportes.ver', 'reportes', 'Consultar reportes');
