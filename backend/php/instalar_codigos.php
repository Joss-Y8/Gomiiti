<?php
// USO ÚNICO: guarda los hashes de los 3 códigos. Edita los códigos, abre este archivo en el
// navegador (http://localhost/gomitti/php/instalar_codigos.php) y BÓRRALO después.
require __DIR__ . '/conexion.php';

if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) { http_response_code(403); exit('Solo desde localhost.'); }

$codigos = [                       // [rol, turno] => código  (CÁMBIALOS)
    'admin|'            => '202611261',
    'cajero|matutino'   => '202611262',
    'cajero|vespertino' => '202611263',
];

if (count(array_unique($codigos)) !== count($codigos)) exit('Los tres códigos deben ser distintos.');
foreach ($codigos as $c) if (strlen($c) < 6) exit('Cada código debe tener al menos 6 caracteres.');
foreach ($codigos as $c) if (str_starts_with($c, 'CAMBIA')) exit('Primero cambia los códigos de ejemplo en este archivo.');

$up = $pdo->prepare('UPDATE usuarios SET codigo_hash = ? WHERE rol = ? AND (turno <=> ?)');
foreach ($codigos as $clave => $codigo) {
    [$rol, $turno] = explode('|', $clave);
    $up->execute([password_hash($codigo, PASSWORD_DEFAULT), $rol, $turno === '' ? null : $turno]);
    echo "$rol " . ($turno ?: '') . ": " . $up->rowCount() . " fila actualizada<br>";
}
echo '<p><strong>Listo. Ahora borra este archivo.</strong></p>';
