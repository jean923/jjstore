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
