<?php
// Cierra el turno abierto y destruye la sesión.
session_start();
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/conexion.php';

if (!empty($_SESSION['id_turno'])) {
    $pdo->prepare('UPDATE turnos SET hora_fin = NOW() WHERE id_turno = ? AND hora_fin IS NULL')
        ->execute([$_SESSION['id_turno']]);
}
$_SESSION = [];
session_destroy();
echo json_encode(['ok'=>true]);
