USE gomitti;

-- CATEGORIAS (id_categoria, nombre)
INSERT INTO categorias (nombre) VALUES
('Gomitas dulces'),
('Gomitas enchiladas'),
('Fruta preparada'),
('Botanas y papas'),
('Bebidas y chamoyadas');

-- PRODUCTOS (id_producto, nombre, id_categoria, precio, disponible, es_temporada)
INSERT INTO productos (nombre, id_categoria, precio, disponible, es_temporada) VALUES
('Vaso de gomitas surtidas (Chico)', 1, 25.00, 1, 0),
('Vaso de gomitas surtidas (Grande)', 1, 45.00, 1, 0),
('Aros de durazno (150 g)', 1, 28.00, 0, 0),
('Manguitos enchilados (150 g)', 2, 30.00, 1, 0),
('Sandias con chile (150 g)', 2, 30.00, 1, 0),
('Vaso de mango preparado', 3, 35.00, 1, 1),
('Vaso de jicama y pepino', 3, 30.00, 1, 0),
('Coctel de fruta mixta', 3, 42.00, 1, 1),
('Tostitos preparados', 4, 48.00, 1, 0),
('Chamoyada de mango', 5, 40.00, 1, 1);

-- INVENTARIO (id_producto, existencia, minimo)
INSERT INTO inventario (id_producto, existencia, minimo) VALUES
(1, 40, 10),
(2, 30, 8),
(3, 0, 5),   -- Agotado para probar alerta
(4, 25, 8),
(5, 6, 8),   -- Bajo stock (6 < 8) para probar alerta
(6, 20, 5),
(7, 18, 5),
(8, 15, 5),
(9, 12, 5),
(10, 22, 6);

-- EXTRAS (id_extra, nombre, tipo, costo, activo)
-- tipo debe ser: 'salsa', 'polvo', 'topping' o 'picante'
INSERT INTO extras (nombre, tipo, costo, activo) VALUES
('Chamoy clasico', 'salsa', 5.00, 1),
('Salsa valentina', 'salsa', 0.00, 1),
('Miguelito', 'polvo', 5.00, 1),
('Tajin', 'polvo', 5.00, 1),
('Gomitas extra', 'topping', 10.00, 1),
('Cacahuates japoneses', 'topping', 8.00, 1),
('Chile en polvo picosito', 'picante', 5.00, 1);

-- PROMOCIONES (id_promo, nombre, precio_promo, descuento_pct, activa)
INSERT INTO promociones (nombre, precio_promo, descuento_pct, activa) VALUES
('Combo Antojo', 58.00, NULL, 1),
('Duo Enchilado', 52.00, NULL, 1),
('Tarde de Temporada 10%', NULL, 10.00, 1);

-- PROMOCION_PRODUCTOS (id_promo, id_producto)
INSERT INTO promocion_productos (id_promo, id_producto) VALUES
(1, 1),  -- Combo Antojo: Vaso gomitas chico
(1, 10), -- Combo Antojo: Chamoyada de mango
(2, 4),  -- Duo Enchilado: Manguitos enchilados
(2, 5),  -- Duo Enchilado: Sandias con chile
(3, 6),  -- Tarde de Temporada: Vaso de mango preparado
(3, 8);  -- Tarde de Temporada: Coctel de fruta mixta