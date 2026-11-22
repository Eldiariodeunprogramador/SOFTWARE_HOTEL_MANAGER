<?php
declare(strict_types=1);

$databaseHost = getenv('HOTEL_DB_HOST') ?: '127.0.0.1';
$databasePort = getenv('HOTEL_DB_PORT') ?: '3306';
$databaseName = getenv('HOTEL_DB_NAME') ?: 'hotelmanager';
$databaseUser = getenv('HOTEL_DB_USER') ?: 'root';
$databasePassword = getenv('HOTEL_DB_PASSWORD');

try {
    $pdo = new PDO(
        "mysql:host={$databaseHost};port={$databasePort};dbname={$databaseName};charset=utf8mb4",
        $databaseUser,
        $databasePassword === false ? '' : $databasePassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $exception) {
    error_log('Hotel Manager database connection failed: ' . $exception->getMessage());
    http_response_code(500);
    exit('No se pudo conectar con la base de datos. Verifique la configuración de conexión.');
}
