CREATE DATABASE IF NOT EXISTS gomitti CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; 
USE gomitti; 

CREATE TABLE IF NOT EXISTS usuarios(
    id_usuario INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(50) NOT NULL, 
    rol ENUM('admin', 'cajero') NOT NULL, 
    turno ENUM('matutino', 'vespertino') NULL, 
    codigo_hash VARCHAR(255) NOT NULL, 
    activo TINYINT(1) NOT NULL DEFAULT 1, 
    UNIQUE KEY uq_rol_turno (rol, turno), 
    CONSTRAINT ck_turno_rol CHECK ((rol='admin' AND turno IS NULL) OR (rol='cajero' AND turno IS NOT NULL))
); 

CREATE TABLE IF NOT EXISTS turnos(
    id_turno INT AUTO_INCREMENT PRIMARY KEY, 
    id_usuario INT NOT NULL, 
    fecha DATE NOT NULL DEFAULT (CURRENT_DATE), 
    hora_inicio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, 
    hora_fin DATETIME NULL,
    FOREIGN KEY(id_usuario) REFERENCES usuarios(id_usuario)
)

CREATE TABLE IF NOT EXISTS intentos_login(
    id_intento INT AUTO_INCREMENT PRIMARY KEY, 
    ip VARCHAR(45) NOT NULL, 
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, 
    exitoso TINYINT(1) NOT NULL, 
    INDEX(ip, fecha)
); 

CREATE TABLE IF NOT EXISTS categorias(
    id_categoria INT AUTO_INCREMENT PRIMARY KEY, 
    nombre VARCHAR(40) NOT NULL UNIQUE
); 

CREATE TABLE IF NOT EXISTS productos(
    id_producto INT AUTO_INCREMENT PRIMARY KEY, 
    nombre VARCHAR(80) NOT NULL, 
    id_categoria INT NOT NULL, 
    precio DECIMAL(8,2) NOT NULL CHECK (precio >=0), 
    disponible TINYINT(1) NOT NULL DEFAULT 1, 
    es_temporada TINYINT(1) NOT NULL DEFAULT 0, 
    FOREIGN KEY(id_categoria) REFERENCES categorias(id_categoria)
); 

CREATE TABLE IF NOT EXISTS inventario(
    id_producto INT PRIMARY KEY, 
    existencia INT NOT NULL DEFAULT 0 CHECK (existencia >= 0), 
    minimo INT NOT NULL DEFAULT 5, 
    FOREIGN KEY(id_producto) REFERENCES productos(id_producto) 
); 

CREATE TABLE IF NOT EXISTS movimientos_inventario(
    id_mov INT AUTO_INCREMENT PRIMARY KEY, 
    id_producto INT NOT NULL, 
    tipo ENUM('entrada', 'ajuste', 'venta') not null, 
    cantidad INT NOT NULL, 
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, 
    id_usuario INT NOT NULL, 
    FOREIGN KEY(id_producto) REFERENCES productos(id_producto), 
    FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario)
); 

CREATE TABLE IF NOT EXISTS extras(
  id_extra INT AUTO_INCREMENT PRIMARY KEY,
  nombre   VARCHAR(40) NOT NULL,
  tipo     ENUM('salsa','polvo','topping','picante') NOT NULL,
  costo    DECIMAL(6,2) NOT NULL DEFAULT 0,
  activo   TINYINT(1) NOT NULL DEFAULT 1
);
CREATE TABLE IF NOT EXISTS promociones(
  id_promo      INT AUTO_INCREMENT PRIMARY KEY,
  nombre        VARCHAR(80) NOT NULL,
  precio_promo  DECIMAL(8,2) NULL,
  descuento_pct DECIMAL(5,2) NULL,
  activa        TINYINT(1) NOT NULL DEFAULT 1
);
CREATE TABLE IF NOT EXISTS promocion_productos(
  id_promo INT, id_producto INT, PRIMARY KEY(id_promo, id_producto),
  FOREIGN KEY(id_promo)    REFERENCES promociones(id_promo),
  FOREIGN KEY(id_producto) REFERENCES productos(id_producto)
);

CREATE TABLE IF NOT EXISTS ventas(
  id_venta    INT AUTO_INCREMENT PRIMARY KEY,
  id_turno    INT NOT NULL,                                -- de aquí sale quién y en qué turno
  fecha_hora  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  metodo_pago ENUM('efectivo','tarjeta') NOT NULL,
  total       DECIMAL(9,2) NOT NULL,
  recibido    DECIMAL(9,2) NULL,                           -- solo efectivo
  cambio      DECIMAL(9,2) NULL,                           -- solo efectivo
  FOREIGN KEY(id_turno) REFERENCES turnos(id_turno)
);
CREATE TABLE IF NOT EXISTS detalle_venta(
  id_detalle  INT AUTO_INCREMENT PRIMARY KEY,
  id_venta    INT NOT NULL,
  id_producto INT NOT NULL,
  cantidad    INT NOT NULL CHECK (cantidad > 0),
  precio_unit DECIMAL(8,2) NOT NULL,                       -- precio al momento de vender
  nota        VARCHAR(255) NULL,
  FOREIGN KEY(id_venta)    REFERENCES ventas(id_venta),
  FOREIGN KEY(id_producto) REFERENCES productos(id_producto)
);
CREATE TABLE IF NOT EXISTS detalle_extras(
  id_detalle INT, id_extra INT, costo_aplicado DECIMAL(6,2) NOT NULL DEFAULT 0,
  PRIMARY KEY(id_detalle, id_extra),
  FOREIGN KEY(id_detalle) REFERENCES detalle_venta(id_detalle),
  FOREIGN KEY(id_extra)   REFERENCES extras(id_extra)
);