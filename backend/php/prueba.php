<?php
require __DIR__ . '/conexion.php';
echo 'Conexión correcta. Tablas: ' . $pdo->query('SHOW TABLES')->rowCount();