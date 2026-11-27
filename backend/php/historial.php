<?php
// Historial de ventas.
//  - Cajero: solo las ventas de SU turno actual; sin extras, notas ni acumulados.
//  - Admin: todas las ventas, con filtros (fecha, método de pago), extras/notas y acumulados.
// La diferencia se aplica AQUÍ, no solo ocultando columnas en el navegador.
require __DIR__ . '/conexion.php';
require __DIR__ . '/seguridad.php';
requerirSesion();

$esAdmin = ($_SESSION['rol'] === 'admin');
$where = []; $params = [];

if ($esAdmin) {
    $fecha = $_GET['fecha'] ?? '';
    if ($fecha !== '') {
        $d = DateTime::createFromFormat('Y-m-d', $fecha);
        if (!$d || $d->format('Y-m-d') !== $fecha) responder(400, ['ok'=>false,'mensaje'=>'Fecha inválida.']);
        $where[]  = 'v.fecha_hora >= ? AND v.fecha_hora < ?';
        $params[] = $fecha;
        $params[] = $d->modify('+1 day')->format('Y-m-d');
    }
    $pago = strtolower($_GET['pago'] ?? '');
    if ($pago !== '') {
        if (!in_array($pago, ['efectivo', 'tarjeta'], true)) responder(400, ['ok'=>false,'mensaje'=>'Método de pago inválido.']);
        $where[]  = 'v.metodo_pago = ?';
        $params[] = $pago;
    }
} else {
    $where[]  = 'v.id_turno = ?';                    // el cajero NO puede pedir otro turno
    $params[] = $_SESSION['id_turno'] ?? 0;
}
$sqlWhere = $where ? 'WHERE ' . implode(' AND ', $where) : '';

try {
    // Ventas (las 200 más recientes)
    $st = $pdo->prepare("SELECT v.id_venta, v.fecha_hora, v.metodo_pago, v.total
                         FROM ventas v $sqlWhere ORDER BY v.id_venta DESC LIMIT 200");
    $st->execute($params);
    $ventas = []; $idx = [];
    foreach ($st as $f) {
        $idx[(int)$f['id_venta']] = count($ventas);
        $ventas[] = [
            'folio'       => (int)$f['id_venta'],
            'fecha_hora'  => $f['fecha_hora'],
            'pago'        => $f['metodo_pago'],
            'total'       => (float)$f['total'],
            'lineas'      => [],
        ];
    }

    // Líneas de cada venta (+ extras y notas solo para admin)
    if ($ventas) {
        $ids = array_keys($idx);
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $st  = $pdo->prepare("SELECT dv.id_venta, dv.id_detalle, p.nombre, dv.cantidad, dv.nota, e.nombre AS extra
                              FROM detalle_venta dv
                              JOIN productos p ON p.id_producto = dv.id_producto
                              LEFT JOIN detalle_extras de ON de.id_detalle = dv.id_detalle
                              LEFT JOIN extras e ON e.id_extra = de.id_extra
                              WHERE dv.id_venta IN ($ph)
                              ORDER BY dv.id_detalle, e.nombre");
        $st->execute($ids);
        $porDetalle = [];                               // id_detalle => posición dentro de la venta
        foreach ($st as $f) {
            $v = $idx[(int)$f['id_venta']];
            $k = (int)$f['id_detalle'];
            if (!isset($porDetalle[$k])) {
                $linea = ['nombre' => $f['nombre'], 'cantidad' => (int)$f['cantidad']];
                if ($esAdmin) { $linea['extras'] = []; $linea['nota'] = $f['nota']; }
                $ventas[$v]['lineas'][] = $linea;
                $porDetalle[$k] = [$v, count($ventas[$v]['lineas']) - 1];
            }
            if ($esAdmin && $f['extra'] !== null) {
                [$vv, $ll] = $porDetalle[$k];
                $ventas[$vv]['lineas'][$ll]['extras'][] = $f['extra'];
            }
        }
    }

    $resp = ['ok' => true, 'ventas' => $ventas];

    // Acumulados: solo admin, sobre TODAS las ventas del filtro (no solo las 200 mostradas)
    if ($esAdmin) {
        $st = $pdo->prepare("SELECT COUNT(*) AS n,
                    COALESCE(SUM(v.total), 0) AS total,
                    COALESCE(SUM(CASE WHEN v.metodo_pago = 'efectivo' THEN v.total END), 0) AS efectivo,
                    COALESCE(SUM(CASE WHEN v.metodo_pago = 'tarjeta'  THEN v.total END), 0) AS tarjeta
                    FROM ventas v $sqlWhere");
        $st->execute($params);
        $r = $st->fetch();
        $resp['resumen'] = [
            'ventas'   => (int)$r['n'],
            'total'    => (float)$r['total'],
            'efectivo' => (float)$r['efectivo'],
            'tarjeta'  => (float)$r['tarjeta'],
        ];
    }
    responder(200, $resp);
} catch (PDOException $e) {
    error_log('Gomitti historial: ' . $e->getMessage());
    responder(500, ['ok' => false, 'mensaje' => 'No se pudo cargar el historial.']);
}
?>