<?php
// Productos que el cajero puede vender: disponibles y con existencia.
require __DIR__ . '/conexion.php';
require __DIR__ . '/seguridad.php';
requerirSesion();

try {
    $sql = "SELECT p.id_producto, p.nombre, p.precio, c.nombre AS categoria, i.existencia
            FROM productos p
            JOIN categorias c ON c.id_categoria = p.id_categoria
            JOIN inventario i ON i.id_producto  = p.id_producto
            WHERE p.disponible = 1 AND i.existencia > 0
            ORDER BY c.nombre, p.nombre";
    $productos = [];
    foreach ($pdo->query($sql) as $f) {
        $productos[] = [
            'id_producto' => (int)$f['id_producto'],
            'nombre'      => $f['nombre'],
            'precio'      => (float)$f['precio'],
            'categoria'   => $f['categoria'],
            'existencia'  => (int)$f['existencia'],
        ];
    }
    responder(200, ['ok' => true, 'productos' => $productos]);
} catch (PDOException $e) {
    error_log('Gomitti productos: ' . $e->getMessage());
    responder(500, ['ok' => false, 'mensaje' => 'No se pudieron cargar los productos.']);
}
?>