<?php
// Guardia común para todos los endpoints. Uso:
//   require __DIR__ . '/conexion.php';
//   require __DIR__ . '/seguridad.php';
//   requerirSesion();            // cualquier usuario con sesión
//   requerirSesion(['admin']);   // solo admin
function responder($http, $datos) {
    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($datos);
    exit;
}

function requerirSesion(array $roles = []) {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
    if (empty($_SESSION['id_usuario'])) {
        responder(401, ['ok' => false, 'mensaje' => 'Sesión no iniciada.']);
    }
    if ($roles && !in_array($_SESSION['rol'], $roles, true)) {
        responder(403, ['ok' => false, 'mensaje' => 'No tienes permiso para esta acción.']);
    }
}