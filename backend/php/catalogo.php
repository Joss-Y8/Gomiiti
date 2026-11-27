<?php
// Catálogo y promociones: únicamente Administrador.
require __DIR__ . '/conexion.php';
require __DIR__ . '/seguridad.php';
requerirSesion(['admin']);

function catalogoEntrada() {
    $datos = json_decode(file_get_contents('php://input'), true);
    if (!is_array($datos)) responder(400, ['ok'=>false, 'mensaje'=>'Datos inválidos.']);
    return $datos;
}

$metodo = $_SERVER['REQUEST_METHOD'];

try {
    if ($metodo === 'GET') {
        $categorias = [];
        foreach ($pdo->query('SELECT id_categoria, nombre FROM categorias ORDER BY nombre') as $f) {
            $categorias[] = ['id_categoria'=>(int)$f['id_categoria'], 'nombre'=>$f['nombre']];
        }

        $productos = [];
        $sql = "SELECT p.id_producto, p.nombre, p.id_categoria, c.nombre AS categoria,
                       p.precio, p.disponible, p.es_temporada,
                       COALESCE(i.existencia,0) AS existencia
                FROM productos p
                JOIN categorias c ON c.id_categoria=p.id_categoria
                LEFT JOIN inventario i ON i.id_producto=p.id_producto
                ORDER BY c.nombre, p.nombre";
        foreach ($pdo->query($sql) as $f) {
            $productos[] = [
                'id_producto'=>(int)$f['id_producto'],
                'nombre'=>$f['nombre'],
                'id_categoria'=>(int)$f['id_categoria'],
                'categoria'=>$f['categoria'],
                'precio'=>(float)$f['precio'],
                'disponible'=>(bool)$f['disponible'],
                'es_temporada'=>(bool)$f['es_temporada'],
                'existencia'=>(int)$f['existencia']
            ];
        }

        $promos = [];
        $sql = "SELECT pr.id_promo, pr.nombre, pr.precio_promo, pr.descuento_pct, pr.activa,
                       GROUP_CONCAT(pp.id_producto ORDER BY pp.id_producto) AS ids_productos,
                       GROUP_CONCAT(p.nombre ORDER BY p.nombre SEPARATOR '|||') AS nombres_productos
                FROM promociones pr
                LEFT JOIN promocion_productos pp ON pp.id_promo=pr.id_promo
                LEFT JOIN productos p ON p.id_producto=pp.id_producto
                GROUP BY pr.id_promo
                ORDER BY pr.id_promo DESC";
        foreach ($pdo->query($sql) as $f) {
            $promos[] = [
                'id_promo'=>(int)$f['id_promo'],
                'nombre'=>$f['nombre'],
                'precio_promo'=>$f['precio_promo'] !== null ? (float)$f['precio_promo'] : null,
                'descuento_pct'=>$f['descuento_pct'] !== null ? (float)$f['descuento_pct'] : null,
                'activa'=>(bool)$f['activa'],
                'ids_productos'=>$f['ids_productos'] ? array_map('intval', explode(',', $f['ids_productos'])) : [],
                'productos'=>$f['nombres_productos'] ? explode('|||', $f['nombres_productos']) : []
            ];
        }
        responder(200, ['ok'=>true, 'categorias'=>$categorias, 'productos'=>$productos, 'promociones'=>$promos]);
    }

    if ($metodo !== 'POST') responder(405, ['ok'=>false, 'mensaje'=>'Método no permitido.']);
    $in = catalogoEntrada();
    $accion = $in['accion'] ?? '';

    if ($accion === 'crear_producto') {
        $nombre = trim($in['nombre'] ?? '');
        $idcat = filter_var($in['id_categoria'] ?? null, FILTER_VALIDATE_INT);
        $precio = filter_var($in['precio'] ?? null, FILTER_VALIDATE_FLOAT);
        $disponible = !empty($in['disponible']) ? 1 : 0;
        $temporada = !empty($in['es_temporada']) ? 1 : 0;
        if ($nombre === '' || mb_strlen($nombre) > 80) responder(400, ['ok'=>false, 'mensaje'=>'El nombre es obligatorio y debe tener máximo 80 caracteres.']);
        if (!$idcat || $idcat < 1) responder(400, ['ok'=>false, 'mensaje'=>'Selecciona una categoría válida.']);
        if ($precio === false || $precio < 0 || $precio > 999999.99) responder(400, ['ok'=>false, 'mensaje'=>'El precio debe ser un número válido mayor o igual a cero.']);
        $st=$pdo->prepare('SELECT 1 FROM categorias WHERE id_categoria=?'); $st->execute([$idcat]);
        if (!$st->fetchColumn()) responder(400, ['ok'=>false, 'mensaje'=>'La categoría seleccionada no existe.']);
        $pdo->beginTransaction();
        $st=$pdo->prepare('INSERT INTO productos(nombre,id_categoria,precio,disponible,es_temporada) VALUES(?,?,?,?,?)');
        $st->execute([$nombre,$idcat,$precio,$disponible,$temporada]);
        $idp=(int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO inventario(id_producto,existencia,minimo) VALUES(?,0,5)')->execute([$idp]);
        $pdo->commit();
        responder(201, ['ok'=>true, 'mensaje'=>'Producto agregado. Se creó con existencia inicial de 0; registra la entrada en Inventario.', 'id_producto'=>$idp]);
    }

    if ($accion === 'cambiar_disponibilidad') {
        $idp = filter_var($in['id_producto'] ?? null, FILTER_VALIDATE_INT);
        if (!$idp || $idp < 1) responder(400, ['ok'=>false, 'mensaje'=>'Producto inválido.']);
        $st=$pdo->prepare('UPDATE productos SET disponible=IF(disponible=1,0,1) WHERE id_producto=?');
        $st->execute([$idp]);
        if (!$st->rowCount()) responder(404, ['ok'=>false, 'mensaje'=>'No se encontró el producto.']);
        responder(200, ['ok'=>true, 'mensaje'=>'Disponibilidad actualizada.']);
    }

    if ($accion === 'eliminar_producto') {
        $idp = filter_var($in['id_producto'] ?? null, FILTER_VALIDATE_INT);
        if (!$idp || $idp < 1) responder(400, ['ok'=>false, 'mensaje'=>'Producto inválido.']);
        // Se evita borrar registros referenciados por ventas o promociones.
        $st=$pdo->prepare('SELECT 1 FROM detalle_venta WHERE id_producto=? LIMIT 1'); $st->execute([$idp]);
        if ($st->fetchColumn()) responder(409, ['ok'=>false, 'mensaje'=>'Este producto ya aparece en ventas. Para conservar el historial, desactiva su disponibilidad en lugar de eliminarlo.']);
        $st=$pdo->prepare('SELECT 1 FROM movimientos_inventario WHERE id_producto=? LIMIT 1'); $st->execute([$idp]);
        if ($st->fetchColumn()) responder(409, ['ok'=>false, 'mensaje'=>'Este producto ya tiene movimientos de inventario. Para conservar la trazabilidad, desactiva su disponibilidad en lugar de eliminarlo.']);
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM promocion_productos WHERE id_producto=?')->execute([$idp]);
        $pdo->prepare('DELETE FROM inventario WHERE id_producto=?')->execute([$idp]);
        $st=$pdo->prepare('DELETE FROM productos WHERE id_producto=?'); $st->execute([$idp]);
        if (!$st->rowCount()) { $pdo->rollBack(); responder(404, ['ok'=>false, 'mensaje'=>'No se encontró el producto.']); }
        $pdo->commit();
        responder(200, ['ok'=>true, 'mensaje'=>'Producto eliminado.']);
    }

    if ($accion === 'crear_promocion') {
        $nombre=trim($in['nombre'] ?? '');
        $precio=($in['precio_promo'] ?? '') === '' ? null : filter_var($in['precio_promo'], FILTER_VALIDATE_FLOAT);
        $descuento=($in['descuento_pct'] ?? '') === '' ? null : filter_var($in['descuento_pct'], FILTER_VALIDATE_FLOAT);
        $ids=$in['productos'] ?? [];
        if ($nombre === '' || mb_strlen($nombre)>80) responder(400, ['ok'=>false, 'mensaje'=>'El nombre de la promoción es obligatorio (máximo 80 caracteres).']);
        if (($precio === null && $descuento === null) || ($precio !== null && $precio === false) || ($descuento !== null && $descuento === false))
            responder(400, ['ok'=>false, 'mensaje'=>'Indica un precio promocional o un descuento.']);
        if ($precio !== null && ($precio < 0 || $precio > 999999.99)) responder(400, ['ok'=>false, 'mensaje'=>'El precio promocional no es válido.']);
        if ($descuento !== null && ($descuento <= 0 || $descuento > 100)) responder(400, ['ok'=>false, 'mensaje'=>'El descuento debe ser mayor a 0 y hasta 100%.']);
        if ($precio !== null && $descuento !== null) responder(400, ['ok'=>false, 'mensaje'=>'Elige precio promocional o porcentaje de descuento, no ambos.']);
        if (!is_array($ids) || count($ids)<1) responder(400, ['ok'=>false, 'mensaje'=>'Selecciona al menos un producto.']);
        $ids=array_values(array_unique(array_filter(array_map('intval',$ids), fn($x)=>$x>0)));
        if (!$ids) responder(400, ['ok'=>false, 'mensaje'=>'Selecciona productos válidos.']);
        $marks=implode(',', array_fill(0,count($ids),'?'));
        $st=$pdo->prepare("SELECT COUNT(*) FROM productos WHERE id_producto IN ($marks)"); $st->execute($ids);
        if ((int)$st->fetchColumn() !== count($ids)) responder(400, ['ok'=>false, 'mensaje'=>'Uno o más productos no existen.']);
        $pdo->beginTransaction();
        $st=$pdo->prepare('INSERT INTO promociones(nombre,precio_promo,descuento_pct,activa) VALUES(?,?,?,1)');
        $st->execute([$nombre,$precio,$descuento]);
        $idpr=(int)$pdo->lastInsertId();
        $st=$pdo->prepare('INSERT INTO promocion_productos(id_promo,id_producto) VALUES(?,?)');
        foreach($ids as $idp) $st->execute([$idpr,$idp]);
        $pdo->commit();
        responder(201, ['ok'=>true, 'mensaje'=>'Promoción creada.']);
    }

    if ($accion === 'cambiar_promocion') {
        $idpr=filter_var($in['id_promo'] ?? null, FILTER_VALIDATE_INT);
        if (!$idpr || $idpr<1) responder(400, ['ok'=>false, 'mensaje'=>'Promoción inválida.']);
        $st=$pdo->prepare('UPDATE promociones SET activa=IF(activa=1,0,1) WHERE id_promo=?'); $st->execute([$idpr]);
        if (!$st->rowCount()) responder(404, ['ok'=>false, 'mensaje'=>'No se encontró la promoción.']);
        responder(200, ['ok'=>true, 'mensaje'=>'Estado de promoción actualizado.']);
    }

    if ($accion === 'eliminar_promocion') {
        $idpr=filter_var($in['id_promo'] ?? null, FILTER_VALIDATE_INT);
        if (!$idpr || $idpr<1) responder(400, ['ok'=>false, 'mensaje'=>'Promoción inválida.']);
        $pdo->beginTransaction();
        $pdo->prepare('DELETE FROM promocion_productos WHERE id_promo=?')->execute([$idpr]);
        $st=$pdo->prepare('DELETE FROM promociones WHERE id_promo=?'); $st->execute([$idpr]);
        if (!$st->rowCount()) { $pdo->rollBack(); responder(404, ['ok'=>false, 'mensaje'=>'No se encontró la promoción.']); }
        $pdo->commit();
        responder(200, ['ok'=>true, 'mensaje'=>'Promoción eliminada.']);
    }

    responder(400, ['ok'=>false, 'mensaje'=>'Acción desconocida.']);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Gomitti catálogo: '.$e->getMessage());
    responder(500, ['ok'=>false, 'mensaje'=>'No se pudo completar la operación. Verifica la estructura de la base de datos.']);
}
?>
