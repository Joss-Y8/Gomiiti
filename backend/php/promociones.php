<?php
// Precio vigente de cada producto considerando las promociones ACTIVAS.
// Si una promoción tiene requiere_combo = 1, solo aplica cuando el pedido
// incluye TODOS los productos asociados a esa promoción (al menos 1 unidad de cada uno).
// Si no se cumple, cada producto conserva su precio normal.
function aplicarPromos(PDO $pdo, array $regularCents, array $cantidades = []) {
    $res = [];
    foreach ($regularCents as $id => $c) {
        $res[$id] = ['final' => $c, 'promo' => null];
    }
    if (!$regularCents) return $res;

    $sql = "SELECT pr.id_promo, pr.nombre, pr.precio_promo, pr.descuento_pct, pr.requiere_combo,
                   pp.id_producto
            FROM promociones pr
            JOIN promocion_productos pp ON pp.id_promo = pr.id_promo
            WHERE pr.activa = 1";
    $stmt = $pdo->query($sql);

    // Agrupar productos por promoción
    $promos = [];
    foreach ($stmt as $f) {
        $idPromo = (int)$f['id_promo'];
        $idProd  = (int)$f['id_producto'];
        if (!isset($promos[$idPromo])) {
            $promos[$idPromo] = [
                'nombre'         => $f['nombre'],
                'precio_promo'   => $f['precio_promo'] !== null ? (float)$f['precio_promo'] : null,
                'descuento_pct'  => $f['descuento_pct'] !== null ? (float)$f['descuento_pct'] : null,
                'requiere_combo' => (int)$f['requiere_combo'] === 1,
                'productos'      => []
            ];
        }
        $promos[$idPromo]['productos'][] = $idProd;
    }

    foreach ($promos as $promo) {
        // Verificar si cumple condición de combo (todos los productos de la promo presentes en el pedido)
        if ($promo['requiere_combo']) {
            $cumpleCombo = true;
            foreach ($promo['productos'] as $idProd) {
                if (($cantidades[$idProd] ?? 0) < 1) {
                    $cumpleCombo = false;
                    break;
                }
            }
            if (!$cumpleCombo) continue;
        }

        foreach ($promo['productos'] as $idProd) {
            if (!isset($res[$idProd])) continue;
            $reg = $regularCents[$idProd];

            if ($promo['precio_promo'] !== null) {
                $cand = (int)round($promo['precio_promo'] * 100);
            } elseif ($promo['descuento_pct'] !== null) {
                $cand = (int)round($reg * (100 - $promo['descuento_pct']) / 100);
            } else {
                continue;
            }

            if ($cand >= 0 && $cand < $res[$idProd]['final']) {
                $res[$idProd] = ['final' => $cand, 'promo' => $promo['nombre']];
            }
        }
    }

    return $res;
}