<?php
// Inventario (solo administrador).
//  GET  -> existencias de todos los productos con su estado.
//  POST -> registra una entrada o un ajuste; actualiza existencia y guarda el movimiento (transacción).
require __DIR__ . '/conexion.php';
require __DIR__ . '/seguridad.php';
requerirSesion(['admin']);

function estadoStock($existencia, $minimo) {
    if ($existencia <= 0)        return 'agotado';
    if ($existencia <= $minimo)  return 'bajo';
    return 'disponible';
}

/* ---------------- GET: listado ---------------- */
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $sql = "SELECT p.id_producto, p.nombre, c.nombre AS categoria, p.disponible,
                       COALESCE(i.existencia, 0) AS existencia, COALESCE(i.minimo, 5) AS minimo
                FROM productos p
                JOIN categorias c ON c.id_categoria = p.id_categoria
                LEFT JOIN inventario i ON i.id_producto = p.id_producto
                ORDER BY c.nombre, p.nombre";
        $items = [];
        foreach ($pdo->query($sql) as $f) {
            $ex = (int)$f['existencia']; $mi = (int)$f['minimo'];
            $items[] = [
                'id_producto' => (int)$f['id_producto'],
                'nombre'      => $f['nombre'],
                'categoria'   => $f['categoria'],
                'disponible'  => (bool)$f['disponible'],
                'existencia'  => $ex,
                'minimo'      => $mi,
                'estado'      => estadoStock($ex, $mi),
            ];
        }
        responder(200, ['ok' => true, 'items' => $items]);
    } catch (PDOException $e) {
        error_log('Gomitti inventario GET: ' . $e->getMessage());
        responder(500, ['ok' => false, 'mensaje' => 'No se pudo cargar el inventario.']);
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') responder(405, ['ok' => false, 'mensaje' => 'Método no permitido.']);

/* ---------------- POST: entrada o ajuste ----------------
   entrada: suma "cantidad" a la existencia (cantidad >= 1).
   ajuste : "cantidad" es la existencia REAL contada (>= 0); se guarda la diferencia como movimiento. */
$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) responder(400, ['ok' => false, 'mensaje' => 'Datos inválidos.']);

$tipo = $in['tipo'] ?? '';
if (!in_array($tipo, ['entrada', 'ajuste'], true)) responder(400, ['ok' => false, 'mensaje' => 'Tipo de movimiento inválido.']);
$idp  = filter_var($in['id_producto'] ?? null, FILTER_VALIDATE_INT);
$cant = filter_var($in['cantidad'] ?? null, FILTER_VALIDATE_INT, ['flags' => FILTER_NULL_ON_FAILURE]);
if (!$idp || $idp < 1) responder(400, ['ok' => false, 'mensaje' => 'Elige un producto.']);
if ($cant === null || $cant === false) responder(400, ['ok' => false, 'mensaje' => 'La cantidad debe ser un número entero.']);
if ($cant > 100000) responder(400, ['ok' => false, 'mensaje' => 'La cantidad es demasiado grande.']);
if ($tipo === 'entrada' && $cant < 1) responder(400, ['ok' => false, 'mensaje' => 'La entrada debe ser de al menos 1 unidad.']);
if ($tipo === 'ajuste'  && $cant < 0) responder(400, ['ok' => false, 'mensaje' => 'La existencia real no puede ser negativa.']);

try {
    $pdo->beginTransaction();

    $st = $pdo->prepare('SELECT existencia, minimo FROM inventario WHERE id_producto = ? FOR UPDATE');
    $st->execute([$idp]);
    $inv = $st->fetch();
    if (!$inv) {                                      // producto sin fila de inventario todavía
        $st = $pdo->prepare('SELECT 1 FROM productos WHERE id_producto = ?');
        $st->execute([$idp]);
        if (!$st->fetchColumn()) throw new RuntimeException('El producto no existe.', 404);
        $pdo->prepare('INSERT INTO inventario(id_producto, existencia, minimo) VALUES (?, 0, 5)')->execute([$idp]);
        $inv = ['existencia' => 0, 'minimo' => 5];
    }
    $actual = (int)$inv['existencia'];
    $nueva  = ($tipo === 'entrada') ? $actual + $cant : $cant;
    $delta  = $nueva - $actual;
    if ($delta === 0) throw new RuntimeException('No hay cambios: la existencia ya es ' . $actual . '.', 400);

    $pdo->prepare('UPDATE inventario SET existencia = ? WHERE id_producto = ?')->execute([$nueva, $idp]);
    $pdo->prepare('INSERT INTO movimientos_inventario(id_producto, tipo, cantidad, id_usuario) VALUES (?,?,?,?)')
        ->execute([$idp, $tipo, $delta, $_SESSION['id_usuario']]);
    $pdo->commit();

    responder(200, ['ok' => true, 'existencia' => $nueva, 'estado' => estadoStock($nueva, (int)$inv['minimo'])]);
} catch (RuntimeException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    responder($e->getCode() ?: 400, ['ok' => false, 'mensaje' => $e->getMessage()]);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Gomitti inventario POST: ' . $e->getMessage());
    responder(500, ['ok' => false, 'mensaje' => 'No se pudo registrar el movimiento.']);
}