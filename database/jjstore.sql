-- =====================================================================
--  JJ STORE · BASE DE DATOS COMPLETA (estructura + datos de demostración)
--
--  Es la unión de schema.sql + datos-demo.sql para importar en un solo paso:
--  phpMyAdmin -> Importar -> seleccionar este archivo -> Continuar.
--
--  ATENCIÓN: borra y vuelve a crear las tablas de la base `jjstore`.
--  Úsalo también para reiniciar la demo a su estado original.
-- =====================================================================

-- =====================================================================
--  JJ STORE · Marketplace de moda urbana
--  Esquema de base de datos (MySQL 8 / MariaDB 10.5+)
--
--  ATENCIÓN: este script BORRA y vuelve a crear las tablas de `jjstore`.
--  Ejecutar primero este archivo y luego `datos-demo.sql`.
-- =====================================================================

CREATE DATABASE IF NOT EXISTS jjstore
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE jjstore;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS correos_enviados;
DROP TABLE IF EXISTS pedido_items;
DROP TABLE IF EXISTS pedidos;
DROP TABLE IF EXISTS producto_variantes;
DROP TABLE IF EXISTS productos;
DROP TABLE IF EXISTS categorias;
DROP TABLE IF EXISTS tiendas;
DROP TABLE IF EXISTS usuarios;
SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------------
-- USUARIOS: toda persona registrada. Puede ser compradora o vendedora.
-- ---------------------------------------------------------------------
CREATE TABLE usuarios (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre          VARCHAR(120)    NOT NULL,
    email           VARCHAR(190)    NOT NULL,
    password_hash   VARCHAR(255)    NOT NULL,
    telefono        VARCHAR(30)     NULL,
    rol             VARCHAR(20)     NOT NULL DEFAULT 'comprador',
    creado_en       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_email (email),
    CONSTRAINT chk_usuarios_rol CHECK (rol IN ('comprador', 'vendedor'))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- TIENDAS: perfil de vendedor (1 tienda por usuario vendedor).
-- ---------------------------------------------------------------------
CREATE TABLE tiendas (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id      BIGINT UNSIGNED NOT NULL,
    nombre          VARCHAR(120)    NOT NULL,
    descripcion     VARCHAR(500)    NULL,
    ciudad          VARCHAR(80)     NULL,
    creado_en       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tiendas_usuario (usuario_id),
    UNIQUE KEY uq_tiendas_nombre (nombre),
    CONSTRAINT fk_tiendas_usuario FOREIGN KEY (usuario_id)
        REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- CATEGORÍAS del catálogo.
-- ---------------------------------------------------------------------
CREATE TABLE categorias (
    id      INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    nombre  VARCHAR(60)      NOT NULL,
    slug    VARCHAR(60)      NOT NULL,
    orden   TINYINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_categorias_slug (slug)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- PRODUCTOS publicados por cada tienda.
-- ---------------------------------------------------------------------
CREATE TABLE productos (
    id              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tienda_id       BIGINT UNSIGNED NOT NULL,
    categoria_id    INT UNSIGNED    NOT NULL,
    nombre          VARCHAR(150)    NOT NULL,
    descripcion     TEXT            NULL,
    precio          DECIMAL(12, 2)  NOT NULL,
    imagen          VARCHAR(500)    NOT NULL,
    activo          TINYINT(1)      NOT NULL DEFAULT 1,
    creado_en       DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en  DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_productos_catalogo (activo, categoria_id, creado_en),
    KEY idx_productos_tienda (tienda_id, creado_en),
    CONSTRAINT fk_productos_tienda FOREIGN KEY (tienda_id)
        REFERENCES tiendas (id) ON DELETE CASCADE,
    CONSTRAINT fk_productos_categoria FOREIGN KEY (categoria_id)
        REFERENCES categorias (id) ON DELETE RESTRICT,
    CONSTRAINT chk_productos_precio CHECK (precio > 0)
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- VARIANTES: cada combinación talla + color tiene su propio inventario.
-- ---------------------------------------------------------------------
CREATE TABLE producto_variantes (
    id           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    producto_id  BIGINT UNSIGNED NOT NULL,
    talla        VARCHAR(10)     NOT NULL,
    color        VARCHAR(30)     NOT NULL,
    stock        INT UNSIGNED    NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_variantes_producto (producto_id, talla, color),
    CONSTRAINT fk_variantes_producto FOREIGN KEY (producto_id)
        REFERENCES productos (id) ON DELETE CASCADE
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- PEDIDOS: la compra (simulada) de un comprador.
-- ---------------------------------------------------------------------
CREATE TABLE pedidos (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    codigo           VARCHAR(20)     NOT NULL,
    comprador_id     BIGINT UNSIGNED NOT NULL,
    nombre_envio     VARCHAR(120)    NOT NULL,
    email_contacto   VARCHAR(190)    NOT NULL,
    telefono         VARCHAR(30)     NOT NULL,
    direccion        VARCHAR(200)    NOT NULL,
    ciudad           VARCHAR(80)     NOT NULL,
    departamento     VARCHAR(80)     NOT NULL,
    notas            VARCHAR(300)    NULL,
    metodo_pago      VARCHAR(20)     NOT NULL,
    estado_pago      VARCHAR(20)     NOT NULL,
    detalle_pago     VARCHAR(80)     NOT NULL,
    referencia_pago  VARCHAR(40)     NOT NULL,
    subtotal         DECIMAL(12, 2)  NOT NULL,
    costo_envio      DECIMAL(12, 2)  NOT NULL,
    total            DECIMAL(12, 2)  NOT NULL,
    creado_en        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_pedidos_codigo (codigo),
    KEY idx_pedidos_comprador (comprador_id, creado_en),
    CONSTRAINT fk_pedidos_comprador FOREIGN KEY (comprador_id)
        REFERENCES usuarios (id) ON DELETE RESTRICT,
    CONSTRAINT chk_pedidos_metodo CHECK (metodo_pago IN ('tarjeta', 'pse', 'contraentrega')),
    CONSTRAINT chk_pedidos_estado_pago CHECK (estado_pago IN ('aprobado', 'pendiente'))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- ÍTEMS DEL PEDIDO: guardan una "foto" del producto al momento de la
-- compra (nombre, precio, talla, color), así el historial no cambia si
-- el vendedor edita o elimina el producto después.
-- Un pedido puede tener productos de varias tiendas; cada tienda
-- gestiona el estado de sus propios ítems.
-- ---------------------------------------------------------------------
CREATE TABLE pedido_items (
    id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pedido_id        BIGINT UNSIGNED NOT NULL,
    tienda_id        BIGINT UNSIGNED NOT NULL,
    producto_id      BIGINT UNSIGNED NULL,
    variante_id      BIGINT UNSIGNED NULL,
    nombre_producto  VARCHAR(150)    NOT NULL,
    imagen           VARCHAR(500)    NOT NULL,
    talla            VARCHAR(10)     NOT NULL,
    color            VARCHAR(30)     NOT NULL,
    precio_unitario  DECIMAL(12, 2)  NOT NULL,
    cantidad         INT UNSIGNED    NOT NULL,
    subtotal         DECIMAL(12, 2)  NOT NULL,
    estado           VARCHAR(20)     NOT NULL DEFAULT 'nuevo',
    actualizado_en   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_items_pedido (pedido_id),
    KEY idx_items_tienda (tienda_id, pedido_id),
    KEY idx_items_producto (producto_id),
    KEY idx_items_variante (variante_id),
    CONSTRAINT fk_items_pedido FOREIGN KEY (pedido_id)
        REFERENCES pedidos (id) ON DELETE CASCADE,
    CONSTRAINT fk_items_tienda FOREIGN KEY (tienda_id)
        REFERENCES tiendas (id) ON DELETE RESTRICT,
    CONSTRAINT fk_items_producto FOREIGN KEY (producto_id)
        REFERENCES productos (id) ON DELETE SET NULL,
    CONSTRAINT fk_items_variante FOREIGN KEY (variante_id)
        REFERENCES producto_variantes (id) ON DELETE SET NULL,
    CONSTRAINT chk_items_cantidad CHECK (cantidad > 0),
    CONSTRAINT chk_items_estado CHECK (estado IN ('nuevo', 'preparando', 'enviado', 'entregado', 'cancelado'))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- CORREOS ENVIADOS: bitácora de cada correo (enviado por SMTP o
-- simulado). Permite ver el correo desde la página del pedido.
-- ---------------------------------------------------------------------
CREATE TABLE correos_enviados (
    id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pedido_id     BIGINT UNSIGNED NULL,
    tipo          VARCHAR(20)     NOT NULL,
    destinatario  VARCHAR(190)    NOT NULL,
    asunto        VARCHAR(200)    NOT NULL,
    html          MEDIUMTEXT      NOT NULL,
    estado        VARCHAR(20)     NOT NULL,
    error         VARCHAR(500)    NULL,
    creado_en     DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_correos_pedido (pedido_id, tipo),
    CONSTRAINT fk_correos_pedido FOREIGN KEY (pedido_id)
        REFERENCES pedidos (id) ON DELETE CASCADE,
    CONSTRAINT chk_correos_tipo CHECK (tipo IN ('compra', 'venta', 'estado')),
    CONSTRAINT chk_correos_estado CHECK (estado IN ('enviado', 'simulado', 'error'))
) ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 COLLATE = utf8mb4_unicode_ci;


-- =====================================================================
--  JJ STORE · Datos de demostración
--  Ejecutar DESPUÉS de schema.sql
--
--  Cuentas de prueba (contraseña de todas: Demo1234)
--    vendedor@jjstore.test     -> Vendedor · tienda "JJ STORE Oficial"
--    barrionorte@jjstore.test  -> Vendedor · tienda "Barrio Norte Studio"
--    comprador@jjstore.test    -> Comprador
-- =====================================================================

USE jjstore;

-- ---------------------------------------------------------------------
-- Usuarios
-- ---------------------------------------------------------------------
INSERT INTO usuarios (id, nombre, email, password_hash, telefono, rol) VALUES
    (1, 'Juan José Restrepo', 'vendedor@jjstore.test',    '$2y$10$A0.Yw1Di0NWQ/gO9l5alEutCX5CU0Pw64VGyc6aj1pQJ7KD0epGgC', '3001234567', 'vendedor'),
    (2, 'Valentina Ríos',     'barrionorte@jjstore.test', '$2y$10$A0.Yw1Di0NWQ/gO9l5alEutCX5CU0Pw64VGyc6aj1pQJ7KD0epGgC', '3109876543', 'vendedor'),
    (3, 'Laura Gómez',        'comprador@jjstore.test',   '$2y$10$A0.Yw1Di0NWQ/gO9l5alEutCX5CU0Pw64VGyc6aj1pQJ7KD0epGgC', '3205551234', 'comprador');

-- ---------------------------------------------------------------------
-- Tiendas
-- ---------------------------------------------------------------------
INSERT INTO tiendas (id, usuario_id, nombre, descripcion, ciudad) VALUES
    (1, 1, 'JJ STORE Oficial',    'La colección original de JJ STORE: básicos urbanos y piezas de autor.', 'Medellín'),
    (2, 2, 'Barrio Norte Studio', 'Streetwear bogotano: buzos, chaquetas y accesorios con actitud.',        'Bogotá');

-- ---------------------------------------------------------------------
-- Categorías
-- ---------------------------------------------------------------------
INSERT INTO categorias (id, nombre, slug, orden) VALUES
    (1, 'Camisetas',   'camisetas',   1),
    (2, 'Pantalones',  'pantalones',  2),
    (3, 'Chaquetas',   'chaquetas',   3),
    (4, 'Buzos',       'buzos',       4),
    (5, 'Accesorios',  'accesorios',  5);

-- ---------------------------------------------------------------------
-- Productos
-- ---------------------------------------------------------------------
INSERT INTO productos (id, tienda_id, categoria_id, nombre, descripcion, precio, imagen, creado_en) VALUES
    (1,  1, 1, 'Camiseta Oversize Black',   'Algodón peinado de 240 g, corte oversize y cuello reforzado. El básico que combina con todo.',       65000,  'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?auto=format&fit=crop&w=600&q=80', NOW() - INTERVAL 30 DAY),
    (2,  1, 1, 'Camiseta Urban White',      'Blanca, limpia y pesada. Tejido que no transparenta y mantiene la forma lavado tras lavado.',          55000,  'https://images.unsplash.com/photo-1503341504253-dff4815485f1?auto=format&fit=crop&w=600&q=80', NOW() - INTERVAL 29 DAY),
    (3,  1, 1, 'Camiseta Streetwear',       'Fit relajado con hombro caído. Diseñada para el día a día en la ciudad.',                              70000,  'https://images.unsplash.com/photo-1523398002811-999ca8dec234?auto=format&fit=crop&w=600&q=80', NOW() - INTERVAL 28 DAY),
    (4,  1, 1, 'Camiseta Minimal',          'Sin estampados, sin ruido. Algodón suave y costuras dobles.',                                          60000,  'https://images.unsplash.com/photo-1576566588028-4147f3842f27?auto=format&fit=crop&w=600&q=80', NOW() - INTERVAL 27 DAY),
    (5,  1, 2, 'Pantalón Cargo',            'Seis bolsillos, tela gabardina resistente y bota ajustable con cordón.',                               120000, 'https://images.unsplash.com/photo-1517438476312-10d79c077509?auto=format&fit=crop&w=600&q=80', NOW() - INTERVAL 26 DAY),
    (6,  1, 2, 'Jeans Urban Blue',          'Denim rígido de 13 oz con lavado medio. Corte recto clásico.',                                         110000, 'https://images.unsplash.com/photo-1542272604-787c3835535d?auto=format&fit=crop&w=600&q=80',    NOW() - INTERVAL 25 DAY),
    (7,  1, 3, 'Chaqueta Street',           'Rompevientos impermeable con capucha oculta y cierres YKK.',                                            180000, 'https://images.unsplash.com/photo-1551028719-00167b16eac5?auto=format&fit=crop&w=600&q=80',    NOW() - INTERVAL 24 DAY),
    (8,  1, 3, 'Chaqueta Denim',            'La chaqueta de jean de siempre, con botones metálicos y forro ligero.',                                 160000, 'https://images.unsplash.com/photo-1543076447-215ad9ba6923?auto=format&fit=crop&w=600&q=80',    NOW() - INTERVAL 23 DAY),
    (9,  2, 4, 'Hoodie Gris Essential',     'Felpa perchada de 320 g, capucha doble y bolsillo canguro.',                                           140000, 'https://images.unsplash.com/photo-1556821840-3a63f95609a7?auto=format&fit=crop&w=600&q=80',    NOW() - INTERVAL 20 DAY),
    (10, 2, 5, 'Gorra Trucker White',       'Gorra trucker con malla trasera y broche ajustable. Talla única.',                                      45000,  'https://images.unsplash.com/photo-1588850561407-ed78c282e89b?auto=format&fit=crop&w=600&q=80', NOW() - INTERVAL 19 DAY),
    (11, 2, 3, 'Chaqueta Bomber Terracota', 'Bomber satinada con puños en rib y bolsillo utilitario en la manga.',                                  210000, 'https://images.unsplash.com/photo-1591047139829-d91aecb6caea?auto=format&fit=crop&w=600&q=80', NOW() - INTERVAL 18 DAY),
    (12, 2, 2, 'Jean Recto Dark',           'Denim oscuro de tiro medio y pierna recta. Combina con cualquier tenis.',                               130000, 'https://images.unsplash.com/photo-1624378439575-d8705ad7ae80?auto=format&fit=crop&w=600&q=80', NOW() - INTERVAL 17 DAY),
    (13, 2, 4, 'Buzo Orange Crew',          'Cuello redondo, color vibrante y felpa francesa de 280 g.',                                             115000, 'https://images.unsplash.com/photo-1578587018452-892bacefd3f2?auto=format&fit=crop&w=600&q=80', NOW() - INTERVAL 16 DAY),
    (14, 2, 4, 'Buzo Blanco Básico',        'El buzo blanco que necesitas en el clóset. Suave por dentro, estructurado por fuera.',                  99000,  'https://images.unsplash.com/photo-1620799140408-edc6dcb6d633?auto=format&fit=crop&w=600&q=80', NOW() - INTERVAL 15 DAY),
    (15, 2, 2, 'Jogger Rosa Palo',          'Jogger de tela fluida con pretina elástica y puño en la bota.',                                         105000, 'https://images.unsplash.com/photo-1594633312681-425c7b97ccd1?auto=format&fit=crop&w=600&q=80', NOW() - INTERVAL 14 DAY),
    (16, 2, 3, 'Chaqueta Biker Cuero',      'Chaqueta biker en cuero sintético premium con cremalleras asimétricas.',                                320000, 'https://images.unsplash.com/photo-1521223890158-f9f7c3d5d504?auto=format&fit=crop&w=600&q=80', NOW() - INTERVAL 13 DAY),
    (17, 2, 1, 'Camiseta 705 Black',        'Estampado frontal 705 en serigrafía. Algodón 100 % peinado.',                                           68000,  'https://images.unsplash.com/photo-1618354691373-d851c5c3a990?auto=format&fit=crop&w=600&q=80', NOW() - INTERVAL 12 DAY),
    (18, 2, 1, 'Camiseta Statement',        'Tipografía en el pecho y fit regular. Para decir algo sin hablar.',                                     62000,  'https://images.unsplash.com/photo-1583743814966-8936f5b7be1a?auto=format&fit=crop&w=600&q=80', NOW() - INTERVAL 11 DAY);

-- ---------------------------------------------------------------------
-- Variantes (talla + color + stock)
-- ---------------------------------------------------------------------
INSERT INTO producto_variantes (producto_id, talla, color, stock) VALUES
    (1, 'S', 'Negro', 7), (1, 'M', 'Negro', 10), (1, 'L', 'Negro', 0), (1, 'XL', 'Negro', 3), (1, 'S', 'Gris', 12), (1, 'M', 'Gris', 2), (1, 'L', 'Gris', 5), (1, 'XL', 'Gris', 8),
    (2, 'S', 'Blanco', 1), (2, 'M', 'Blanco', 4), (2, 'L', 'Blanco', 7), (2, 'XL', 'Blanco', 10), (2, 'S', 'Negro', 6), (2, 'M', 'Negro', 9), (2, 'L', 'Negro', 12), (2, 'XL', 'Negro', 2),
    (3, 'S', 'Negro', 8), (3, 'M', 'Negro', 11), (3, 'L', 'Negro', 1), (3, 'XL', 'Negro', 4), (3, 'S', 'Blanco', 0), (3, 'M', 'Blanco', 3), (3, 'L', 'Blanco', 6), (3, 'XL', 'Blanco', 9), (3, 'S', 'Gris', 5), (3, 'M', 'Gris', 8), (3, 'L', 'Gris', 11), (3, 'XL', 'Gris', 1),
    (4, 'S', 'Gris', 2), (4, 'M', 'Gris', 5), (4, 'L', 'Gris', 8), (4, 'XL', 'Gris', 11), (4, 'S', 'Blanco', 7), (4, 'M', 'Blanco', 10), (4, 'L', 'Blanco', 0), (4, 'XL', 'Blanco', 3),
    (5, '30', 'Beige', 9), (5, '32', 'Beige', 12), (5, '34', 'Beige', 2), (5, '36', 'Beige', 5), (5, '30', 'Negro', 1), (5, '32', 'Negro', 4), (5, '34', 'Negro', 7), (5, '36', 'Negro', 10), (5, '30', 'Verde', 6), (5, '32', 'Verde', 9), (5, '34', 'Verde', 12), (5, '36', 'Verde', 2),
    (6, '30', 'Azul', 3), (6, '32', 'Azul', 6), (6, '34', 'Azul', 9), (6, '36', 'Azul', 12),
    (7, 'S', 'Negro', 10), (7, 'M', 'Negro', 0), (7, 'L', 'Negro', 3), (7, 'XL', 'Negro', 6),
    (8, 'S', 'Azul', 4), (8, 'M', 'Azul', 7), (8, 'L', 'Azul', 10), (8, 'XL', 'Azul', 0),
    (9, 'S', 'Gris', 11), (9, 'M', 'Gris', 1), (9, 'L', 'Gris', 4), (9, 'XL', 'Gris', 7), (9, 'S', 'Negro', 3), (9, 'M', 'Negro', 6), (9, 'L', 'Negro', 9), (9, 'XL', 'Negro', 12),
    (10, 'Única', 'Blanco', 5), (10, 'Única', 'Negro', 10),
    (11, 'S', 'Terracota', 12), (11, 'M', 'Terracota', 2), (11, 'L', 'Terracota', 5), (11, 'XL', 'Terracota', 8), (11, 'S', 'Negro', 4), (11, 'M', 'Negro', 7), (11, 'L', 'Negro', 10), (11, 'XL', 'Negro', 0),
    (12, '30', 'Azul', 6), (12, '32', 'Azul', 9), (12, '34', 'Azul', 12), (12, '36', 'Azul', 2), (12, '30', 'Negro', 11), (12, '32', 'Negro', 1), (12, '34', 'Negro', 4), (12, '36', 'Negro', 7),
    (13, 'S', 'Naranja', 0), (13, 'M', 'Naranja', 3), (13, 'L', 'Naranja', 6), (13, 'XL', 'Naranja', 9),
    (14, 'S', 'Blanco', 7), (14, 'M', 'Blanco', 10), (14, 'L', 'Blanco', 0), (14, 'XL', 'Blanco', 3), (14, 'S', 'Gris', 12), (14, 'M', 'Gris', 2), (14, 'L', 'Gris', 5), (14, 'XL', 'Gris', 8),
    (15, 'S', 'Rosa', 1), (15, 'M', 'Rosa', 4), (15, 'L', 'Rosa', 7), (15, 'XL', 'Rosa', 10), (15, 'S', 'Beige', 6), (15, 'M', 'Beige', 9), (15, 'L', 'Beige', 12), (15, 'XL', 'Beige', 2),
    (16, 'S', 'Negro', 8), (16, 'M', 'Negro', 11), (16, 'L', 'Negro', 1), (16, 'XL', 'Negro', 4),
    (17, 'S', 'Negro', 2), (17, 'M', 'Negro', 5), (17, 'L', 'Negro', 8), (17, 'XL', 'Negro', 11),
    (18, 'S', 'Negro', 9), (18, 'M', 'Negro', 12), (18, 'L', 'Negro', 2), (18, 'XL', 'Negro', 5), (18, 'S', 'Blanco', 1), (18, 'M', 'Blanco', 4), (18, 'L', 'Blanco', 7), (18, 'XL', 'Blanco', 10);

-- ---------------------------------------------------------------------
-- Pedidos de ejemplo (para que el panel de vendedor tenga datos)
-- ---------------------------------------------------------------------
INSERT INTO pedidos (id, codigo, comprador_id, nombre_envio, email_contacto, telefono, direccion, ciudad, departamento,
                     metodo_pago, estado_pago, detalle_pago, referencia_pago, subtotal, costo_envio, total, creado_en) VALUES
    (1, 'JJ-7KQ2MX', 3, 'Laura Gómez', 'comprador@jjstore.test', '3205551234', 'Cra 43A # 1-50, apto 802', 'Medellín', 'Antioquia',
        'tarjeta', 'aprobado', 'VISA •••• 4242', 'SIM-8F2K4Q7Z1M', 270000, 0, 270000, NOW() - INTERVAL 6 DAY),
    (2, 'JJ-3HD9PA', 3, 'Laura Gómez', 'comprador@jjstore.test', '3205551234', 'Cra 43A # 1-50, apto 802', 'Medellín', 'Antioquia',
        'pse', 'aprobado', 'PSE · Bancolombia', 'SIM-2X9M4D7K8P', 180000, 12000, 192000, NOW() - INTERVAL 3 DAY),
    (3, 'JJ-9WX4TC', 3, 'Laura Gómez', 'comprador@jjstore.test', '3205551234', 'Cra 43A # 1-50, apto 802', 'Medellín', 'Antioquia',
        'contraentrega', 'pendiente', 'Pago contra entrega', 'SIM-7T3W9C2H5N', 175000, 12000, 187000, NOW() - INTERVAL 1 DAY),
    (4, 'JJ-5RM8VB', 3, 'Laura Gómez', 'comprador@jjstore.test', '3205551234', 'Calle 85 # 11-20', 'Bogotá', 'Cundinamarca',
        'tarjeta', 'aprobado', 'MASTERCARD •••• 4444', 'SIM-4B8V2R6M9J', 300000, 0, 300000, NOW() - INTERVAL 2 HOUR);

INSERT INTO pedido_items (pedido_id, tienda_id, producto_id, variante_id, nombre_producto, imagen, talla, color,
                          precio_unitario, cantidad, subtotal, estado)
SELECT x.pedido_id, p.tienda_id, p.id, v.id, p.nombre, p.imagen, v.talla, v.color, p.precio, x.cantidad, p.precio * x.cantidad, x.estado
FROM (
              SELECT 1 AS pedido_id, 1  AS producto_id, 'M'     AS talla, 'Negro'     AS color, 2 AS cantidad, 'entregado'  AS estado
    UNION ALL SELECT 1, 9,  'L',     'Gris',      1, 'entregado'
    UNION ALL SELECT 2, 7,  'L',     'Negro',     1, 'enviado'
    UNION ALL SELECT 3, 5,  '32',    'Beige',     1, 'nuevo'
    UNION ALL SELECT 3, 2,  'S',     'Blanco',    1, 'nuevo'
    UNION ALL SELECT 4, 11, 'M',     'Terracota', 1, 'preparando'
    UNION ALL SELECT 4, 10, 'Única', 'Blanco',    2, 'preparando'
) AS x
JOIN productos p          ON p.id = x.producto_id
JOIN producto_variantes v ON v.producto_id = p.id AND v.talla = x.talla AND v.color = x.color;
