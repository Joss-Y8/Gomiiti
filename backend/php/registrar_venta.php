<?php
// Registra una venta completa en UNA transacción: ventas + detalle + extras + descuento de inventario.
// El navegador solo manda ids y cantidades; precios, costos y total se leen/recalculan aquí.
require __DIR__ . '/conexion.php';
require __DIR__ . '/seguridad.php';
require __DIR__ . '/promociones.php';
requerirSesion();                                   // cajero o admin

if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(405, ['ok'=>false,'mensaje'=>'Método no permitido.']);

$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) responder(400, ['ok'=>false,'mensaje'=>'Datos inválidos.']);

/* ---------- 1) Validar y normalizar lo que mandó el navegador ---------- */
$metodo = $in['metodo_pago'] ?? '';
if (!in_array($metodo, ['efectivo','tarjeta'], true)) responder(400, ['ok'=>false,'mensaje'=>'Método de pago inválido.']);

$items = $in['items'] ?? null;
if (!is_array($items) || count($items) === 0) responder(400, ['ok'=>false,'mensaje'=>'El pedido está vacío.']);
if (count($items) > 50) responder(400, ['ok'=>false,'mensaje'=>'El pedido tiene demasiados artículos.']);

$lineas = []; $porProducto = []; $idsExtras = [];
foreach ($items as $it) {
    $idp  = filter_var($it['id_producto'] ?? null, FILTER_VALIDATE_INT);
    $cant = filter_var($it['cantidad'] ?? null, FILTER_VALIDATE_INT);
    if (!$idp || $idp < 1 || !$cant || $cant < 1 || $cant > 99) {
        responder(400, ['ok'=>false,'mensaje'=>'Hay cantidades inválidas en el pedido.']);
    }
    $ex = $it['extras'] ?? [];
    if (!is_array($ex)) responder(400, ['ok'=>false,'mensaje'=>'Complementos inválidos.']);
    $ex = array_values(array_unique(array_map('intval', $ex)));
    foreach ($ex as $e) if ($e < 1) responder(400, ['ok'=>false,'mensaje'=>'Complementos inválidos.']);

    $nota = trim((string)($it['nota'] ?? ''));
    if (mb_strlen($nota) > 255) $nota = mb_substr($nota, 0, 255);

    $lineas[] = ['id'=>$idp, 'cant'=>$cant, 'extras'=>$ex, 'nota'=>$nota === '' ? null : $nota];
    $porProducto[$idp] = ($porProducto[$idp] ?? 0) + $cant;
    foreach ($ex as $e) $idsExtras[$e] = true;
}
ksort($porProducto);                                // mismo orden de bloqueo siempre (evita deadlocks)

try {
    /* ---------- 2) Costos de extras desde la BD ---------- */
    $costoExtra = [];                               // id_extra => centavos
    if ($idsExtras) {
        $ids = array_keys($idsExtras);
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $st  = $pdo->prepare("SELECT id_extra, costo FROM extras WHERE activo = 1 AND id_extra IN ($ph)");
        $st->execute($ids);
        foreach ($st as $f) $costoExtra[(int)$f['id_extra']] = (int)round((float)$f['costo'] * 100);
        foreach ($ids as $e) if (!isset($costoExtra[$e])) {
            responder(400, ['ok'=>false,'mensaje'=>'Un complemento del pedido ya no está disponible. Revisa los detalles.']);
        }
    }

    /* ---------- 3) Transacción ---------- */
    $pdo->beginTransaction();

    // El turno de la sesión debe seguir abierto
    $st = $pdo->prepare('SELECT 1 FROM turnos WHERE id_turno = ? AND hora_fin IS NULL');
    $st->execute([$_SESSION['id_turno'] ?? 0]);
    if (!$st->fetchColumn()) throw new RuntimeException('Tu turno ya está cerrado. Inicia sesión de nuevo.', 401);

    // Bloquea y valida cada producto (precio actual, disponibilidad y existencia)
    $info = [];                                     // id => [nombre, centavos]
    $sel = $pdo->prepare('SELECT p.nombre, p.precio, p.disponible, i.existencia
                          FROM productos p JOIN inventario i ON i.id_producto = p.id_producto
                          WHERE p.id_producto = ? FOR UPDATE');
    foreach ($porProducto as $idp => $cantTotal) {
        $sel->execute([$idp]);
        $p = $sel->fetch();
        if (!$p || !(int)$p['disponible']) throw new RuntimeException('Un producto del pedido ya no está disponible.', 409);
        if ((int)$p['existencia'] < $cantTotal) {
            throw new RuntimeException('No hay suficiente existencia de ' . $p['nombre'] . ' (quedan ' . (int)$p['existencia'] . ').', 409);
        }
        $info[$idp] = ['nombre'=>$p['nombre'], 'cent'=>(int)round((float)$p['precio'] * 100)];
    }

    // Precio vigente con promociones activas considerando cantidades/combos del pedido
    $preciosBase = array_map(function ($x) { return $x['cent']; }, $info);
    $vig = aplicarPromos($pdo, $preciosBase, $porProducto);
    foreach ($vig as $idp => $v) $info[$idp]['cent'] = $v['final'];

    // Total en centavos (enteros, sin errores de redondeo)
    $totalC = 0;
    foreach ($lineas as $l) {
        $unit = $info[$l['id']]['cent'];
        foreach ($l['extras'] as $e) $unit += $costoExtra[$e];
        $totalC += $unit * $l['cant'];
    }

    // Pago
    $recibidoC = null; $cambioC = null;
    if ($metodo === 'efectivo') {
        $r = $in['recibido'] ?? null;
        if (!is_numeric($r) || $r < 0) throw new RuntimeException('Indica el efectivo recibido.', 400);
        $recibidoC = (int)round((float)$r * 100);
        if ($recibidoC < $totalC) throw new RuntimeException('El efectivo recibido no cubre el total.', 400);
        $cambioC = $recibidoC - $totalC;
    }
    $m = function ($c) { return $c === null ? null : number_format($c / 100, 2, '.', ''); };

    // Venta
    $pdo->prepare('INSERT INTO ventas(id_turno, metodo_pago, total, recibido, cambio) VALUES (?,?,?,?,?)')
        ->execute([$_SESSION['id_turno'], $metodo, $m($totalC), $m($recibidoC), $m($cambioC)]);
    $idVenta = (int)$pdo->lastInsertId();

    // Detalle, extras, inventario y movimientos
    $insDet = $pdo->prepare('INSERT INTO detalle_venta(id_venta, id_producto, cantidad, precio_unit, nota) VALUES (?,?,?,?,?)');
    $insEx  = $pdo->prepare('INSERT INTO detalle_extras(id_detalle, id_extra, costo_aplicado) VALUES (?,?,?)');
    foreach ($lineas as $l) {
        $insDet->execute([$idVenta, $l['id'], $l['cant'], $m($info[$l['id']]['cent']), $l['nota']]);
        $idDetalle = (int)$pdo->lastInsertId();
        foreach ($l['extras'] as $e) $insEx->execute([$idDetalle, $e, $m($costoExtra[$e])]);
    }
    $updInv = $pdo->prepare('UPDATE inventario SET existencia = existencia - ? WHERE id_producto = ?');
    $insMov = $pdo->prepare("INSERT INTO movimientos_inventario(id_producto, tipo, cantidad, id_usuario) VALUES (?, 'venta', ?, ?)");
    foreach ($porProducto as $idp => $cantTotal) {
        $updInv->execute([$cantTotal, $idp]);
        $insMov->execute([$idp, -$cantTotal, $_SESSION['id_usuario']]);
    }

    $pdo->commit();
    responder(200, ['ok'=>true, 'folio'=>$idVenta, 'total'=>$m($totalC), 'cambio'=>$m($cambioC)]);

} catch (RuntimeException $e) {                     // errores de negocio (stock, pago, turno)
    if ($pdo->inTransaction()) $pdo->rollBack();
    responder($e->getCode() ?: 400, ['ok'=>false, 'mensaje'=>$e->getMessage()]);
} catch (PDOException $e) {                         // errores de BD: se registran, no se muestran
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Gomitti registrar_venta: ' . $e->getMessage());
    responder(500, ['ok'=>false, 'mensaje'=>'No se pudo registrar la venta. Intenta de nuevo.']);
}
?>