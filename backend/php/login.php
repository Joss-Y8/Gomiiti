<?php
// Login por código. Cajero: un código por turno. Admin: un código.
session_start();
header('Content-Type: application/json; charset=utf-8');
require __DIR__ . '/conexion.php';

function responder($http, $datos){ http_response_code($http); echo json_encode($datos); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(405, ['ok'=>false,'mensaje'=>'Método no permitido.']);

$codigo = trim($_POST['codigo'] ?? '');
if ($codigo === '' || strlen($codigo) > 30) responder(400, ['ok'=>false,'mensaje'=>'Escribe tu código.']);

$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// 1) Bloqueo por fuerza bruta: 5 fallos en 15 minutos desde la misma IP
$st = $pdo->prepare('SELECT COUNT(*) FROM intentos_login WHERE ip = ? AND exitoso = 0 AND fecha > (NOW() - INTERVAL 15 MINUTE)');
$st->execute([$ip]);
if ((int)$st->fetchColumn() >= 5) responder(429, ['ok'=>false,'mensaje'=>'Demasiados intentos. Espera 15 minutos.']);

// 2) Buscar el usuario cuyo hash coincide con el código (son solo 3)
$u = null;
foreach ($pdo->query('SELECT id_usuario, nombre, rol, turno, codigo_hash FROM usuarios WHERE activo = 1') as $fila) {
    if (password_verify($codigo, $fila['codigo_hash'])) { $u = $fila; break; }
}

$reg = $pdo->prepare('INSERT INTO intentos_login(ip, exitoso) VALUES (?, ?)');
if (!$u) {
    $reg->execute([$ip, 0]);
    responder(401, ['ok'=>false,'mensaje'=>'Código incorrecto.']);
}
$reg->execute([$ip, 1]);

// 3) Turno: cerrar turnos olvidados de días anteriores y reutilizar el abierto de hoy
$pdo->prepare('UPDATE turnos SET hora_fin = NOW() WHERE id_usuario = ? AND hora_fin IS NULL AND fecha < CURDATE()')
    ->execute([$u['id_usuario']]);
$st = $pdo->prepare('SELECT id_turno FROM turnos WHERE id_usuario = ? AND hora_fin IS NULL ORDER BY id_turno DESC LIMIT 1');
$st->execute([$u['id_usuario']]);
$idTurno = $st->fetchColumn();
if (!$idTurno) {
    $pdo->prepare('INSERT INTO turnos(id_usuario) VALUES (?)')->execute([$u['id_usuario']]);
    $idTurno = $pdo->lastInsertId();
}

// 4) Sesión PHP (nuevo id para evitar fijación de sesión)
session_regenerate_id(true);
$_SESSION['id_usuario'] = (int)$u['id_usuario'];
$_SESSION['rol']        = $u['rol'];
$_SESSION['turno']      = $u['turno'];
$_SESSION['id_turno']   = (int)$idTurno;
$_SESSION['nombre']     = $u['nombre'];

responder(200, ['ok'=>true, 'nombre'=>$u['nombre'], 'rol'=>$u['rol'], 'turno'=>$u['turno']]);
?>