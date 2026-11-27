<?php
// Complementos activos (salsas, polvos, toppings, picante) para detalles.html.
require __DIR__ . '/conexion.php';
require __DIR__ . '/seguridad.php';
requerirSesion();

try {
    $extras = [];
    foreach ($pdo->query("SELECT id_extra, nombre, tipo, costo FROM extras WHERE activo = 1 ORDER BY tipo, nombre") as $f) {
        $extras[] = [
            'id'     => (int)$f['id_extra'],
            'nombre' => $f['nombre'],
            'tipo'   => $f['tipo'],
            'costo'  => (float)$f['costo'],
        ];
    }
    responder(200, ['ok' => true, 'extras' => $extras]);
} catch (PDOException $e) {
    error_log('Gomitti extras: ' . $e->getMessage());
    responder(500, ['ok' => false, 'mensaje' => 'No se pudieron cargar los complementos.']);
}
?>