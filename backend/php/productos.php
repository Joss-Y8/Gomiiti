<?php
// Productos que el cajero puede vender: disponibles, con existencia y con su precio vigente (promociones).
require __DIR__ . '/conexion.php';
require __DIR__ . '/seguridad.php';
require __DIR__ . '/promociones.php';
requerirSesion();

try {
    $sql = "SELECT p.id_producto, p.nombre, p.precio, c.nombre AS categoria, i.existencia
            FROM productos p
            JOIN categorias c ON c.id_categoria = p.id_categoria
            JOIN inventario i ON i.id_producto  = p.id_producto
            WHERE p.disponible = 1 AND i.existencia > 0
            ORDER BY c.nombre, p.nombre";
    $filas = $pdo->query($sql)->fetchAll();

    $regular = [];
    foreach ($filas as $f) $regular[(int)$f['id_producto']] = (int)round((float)$f['precio'] * 100);
    $vig = aplicarPromos($pdo, $regular);

    $productos = [];
    foreach ($filas as $f) {
        $id = (int)$f['id_producto'];
        $productos[] = [
            'id_producto'  => $id,
            'nombre'       => $f['nombre'],
            'precio'       => (float)$f['precio'],               // precio normal
            'precio_final' => $vig[$id]['final'] / 100,          // precio con promoción (o igual al normal)
            'promo'        => $vig[$id]['promo'],                // nombre de la promoción aplicada o null
            'categoria'    => $f['categoria'],
            'existencia'   => (int)$f['existencia'],
        ];
    }
    $promosActivas = [];
    $sqlPromos = "SELECT pr.id_promo, pr.nombre, pr.precio_promo, pr.descuento_pct, pr.requiere_combo,
                         GROUP_CONCAT(pp.id_producto ORDER BY pp.id_producto) AS ids_productos
                  FROM promociones pr
                  JOIN promocion_productos pp ON pp.id_promo = pr.id_promo
                  WHERE pr.activa = 1
                  GROUP BY pr.id_promo";
    foreach ($pdo->query($sqlPromos) as $f) {
        $ids = $f['ids_productos'] ? array_map('intval', explode(',', $f['ids_productos'])) : [];
        if (!$ids) continue;
        $promosActivas[] = [
            'nombre'         => $f['nombre'],
            'precio_promo'   => $f['precio_promo'] !== null ? (float)$f['precio_promo'] : null,
            'descuento_pct'  => $f['descuento_pct'] !== null ? (float)$f['descuento_pct'] : null,
            'requiere_combo' => (int)$f['requiere_combo'] === 1,
            'ids_productos'  => $ids,
        ];
    }

    responder(200, ['ok' => true, 'productos' => $productos, 'promociones' => $promosActivas]);

} catch (PDOException $e) {
    error_log('Gomitti productos: ' . $e->getMessage());
    responder(500, ['ok' => false, 'mensaje' => 'No se pudieron cargar los productos.']);
}
?>