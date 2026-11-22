SET @estados_reservas_activo_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'estados_reservas'
      AND COLUMN_NAME = 'activo'
);

SET @estados_reservas_activo_sql = IF(
    @estados_reservas_activo_exists = 0,
    'ALTER TABLE estados_reservas ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1',
    'SET @estados_reservas_noop = 1'
);

PREPARE estados_reservas_activo_statement FROM @estados_reservas_activo_sql;
EXECUTE estados_reservas_activo_statement;
DEALLOCATE PREPARE estados_reservas_activo_statement;
