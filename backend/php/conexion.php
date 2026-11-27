<?php
const DB_HOST = 'localhost'; 
const DB_NAME = 'gomitti'; 
const DB_USER = 'root'; 
const DB_PASS = 'jojoyrl8'; 

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // errores como excepciones
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // filas como arreglos asociativos
            PDO::ATTR_EMULATE_PREPARES   => false,                    // consultas preparadas reales
        ]
    );
} catch (PDOException $e) {
    error_log('Gomitti BD: ' . $e->getMessage());
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'mensaje' => 'No se pudo conectar con la base de datos.']);
    exit;
}
?>