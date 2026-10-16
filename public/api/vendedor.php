<?php
/**
 * API del panel de vendedor. Todas las acciones exigen sesión de vendedor
 * y SOLO operan sobre los datos de su propia tienda.
 *
 *   GET  vendedor.php?accion=resumen
 *   GET  vendedor.php?accion=productos
 *   POST vendedor.php?accion=guardar-producto     (multipart: crea o edita)
 *   POST vendedor.php?accion=estado-producto      {id, activo}
 *   POST vendedor.php?accion=eliminar-producto    {id}
 *   GET  vendedor.php?accion=ventas&estado=nuevo
 *   POST vendedor.php?accion=estado-venta         {pedido_id, estado}
 *   GET  vendedor.php?accion=tienda
 *   POST vendedor.php?accion=guardar-tienda
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';
require RAIZ . '/app/catalogo.php';
require RAIZ . '/app/imagenes.php';
require RAIZ . '/app/notificaciones.php';

$vendedor = requerir_vendedor();
$tiendaId = $vendedor['tienda_id'];

switch (accion()) {
    case 'resumen':
        exigir_metodo('GET');

        $ventas = consulta(
            "SELECT COALESCE(SUM(subtotal), 0) AS total,
                    COALESCE(SUM(cantidad), 0) AS unidades,
                    COUNT(DISTINCT pedido_id)  AS pedidos,
                    COUNT(DISTINCT CASE WHEN estado IN ('nuevo', 'preparando') THEN pedido_id END) AS por_despachar
             FROM pedido_items
             WHERE tienda_id = ? AND estado <> 'cancelado'",
            [$tiendaId]
        )->fetch();

        $productos = consulta(
            'SELECT COUNT(*) AS total, COALESCE(SUM(activo), 0) AS activos FROM productos WHERE tienda_id = ?',
            [$tiendaId]
        )->fetch();

        $porDia = consulta(
            "SELECT DATE(pe.creado_en) AS dia, SUM(pi.subtotal) AS total
             FROM pedido_items pi
             JOIN pedidos pe ON pe.id = pi.pedido_id
             WHERE pi.tienda_id = ? AND pi.estado <> 'cancelado' AND pe.creado_en >= CURDATE() - INTERVAL 6 DAY
             GROUP BY DATE(pe.creado_en)",
            [$tiendaId]
        )->fetchAll(PDO::FETCH_KEY_PAIR);

        $ultimos7Dias = [];
        for ($i = 6; $i >= 0; $i--) {
            $dia = date('Y-m-d', strtotime("-$i days"));
            $ultimos7Dias[] = ['dia' => $dia, 'total' => (float) ($porDia[$dia] ?? 0)];
        }

        $masVendidos = consulta(
            "SELECT nombre_producto AS nombre, SUM(cantidad) AS unidades, SUM(subtotal) AS total
             FROM pedido_items
             WHERE tienda_id = ? AND estado <> 'cancelado'
             GROUP BY producto_id, nombre_producto
             ORDER BY unidades DESC, total DESC
             LIMIT 5",
            [$tiendaId]
        )->fetchAll();

        $stockBajo = consulta(
            'SELECT p.id AS producto_id, p.nombre, v.talla, v.color, v.stock
             FROM producto_variantes v
             JOIN productos p ON p.id = v.producto_id
             WHERE p.tienda_id = ? AND p.activo = 1 AND v.stock <= 3
             ORDER BY v.stock, p.nombre
             LIMIT 8',
            [$tiendaId]
        )->fetchAll();

        responder([
            'tienda'          => $vendedor['tienda_nombre'],
            'ventas_total'    => (float) $ventas['total'],
            'unidades'        => (int) $ventas['unidades'],
            'pedidos'         => (int) $ventas['pedidos'],
            'por_despachar'   => (int) $ventas['por_despachar'],
            'productos'       => (int) $productos['total'],
            'productos_activos' => (int) $productos['activos'],
            'ultimos_7_dias'  => $ultimos7Dias,
            'mas_vendidos'    => array_map(fn ($f) => [
                'nombre' => $f['nombre'], 'unidades' => (int) $f['unidades'], 'total' => (float) $f['total'],
            ], $masVendidos),
            'stock_bajo'      => array_map(fn ($f) => array_merge($f, ['producto_id' => (int) $f['producto_id'], 'stock' => (int) $f['stock']]), $stockBajo),
        ]);

    case 'productos':
        exigir_metodo('GET');

        $productos = consulta(
            'SELECT p.id, p.nombre, p.descripcion, p.precio, p.imagen, p.activo, p.categoria_id,
                    c.nombre AS categoria, p.creado_en,
                    (SELECT COALESCE(SUM(pi.cantidad), 0) FROM pedido_items pi
                     WHERE pi.producto_id = p.id AND pi.estado <> \'cancelado\') AS vendidos
             FROM productos p
             JOIN categorias c ON c.id = p.categoria_id
             WHERE p.tienda_id = ?
             ORDER BY p.creado_en DESC, p.id DESC',
            [$tiendaId]
        )->fetchAll();

        $variantes = variantes_por_producto(array_map('intval', array_column($productos, 'id')));

        responder(array_map(function (array $p) use ($variantes) {
            $lista = $variantes[(int) $p['id']] ?? [];

            return [
                'id'           => (int) $p['id'],
                'nombre'       => $p['nombre'],
                'descripcion'  => $p['descripcion'],
                'precio'       => (float) $p['precio'],
                'imagen'       => $p['imagen'],
                'activo'       => (bool) $p['activo'],
                'categoria_id' => (int) $p['categoria_id'],
                'categoria'    => $p['categoria'],
                'vendidos'     => (int) $p['vendidos'],
                'stock_total'  => array_sum(array_column($lista, 'stock')),
                'variantes'    => $lista,
            ];
        }, $productos));

    case 'guardar-producto':
        exigir_metodo('POST');
        $datos = entrada();
        $v = new Validador($datos);

        $id = filter_var($datos['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
        $actual = $id ? producto_de_la_tienda($id, $tiendaId) : null;

        $nombre = $v->texto('nombre', 'El nombre', 3, 150);
        $descripcion = $v->texto('descripcion', 'La descripción', 0, 2000, requerido: false);
        $precio = $v->entero('precio', 'El precio', 1000, 50000000);
        $categoriaId = $v->entero('categoria_id', 'La categoría', 1);
        $activo = in_array($datos['activo'] ?? '1', ['1', 'true', 'on', 1, true], true);

        if ($categoriaId && !consulta('SELECT 1 FROM categorias WHERE id = ?', [$categoriaId])->fetchColumn()) {
            $v->agregarError('categoria_id', 'Selecciona una categoría válida.');
        }

        $variantes = validar_variantes($v, $datos['variantes'] ?? '[]');

        // Imagen: archivo subido, URL externa o (al editar) la que ya tenía.
        $imagenNueva = null;
        $urlImagen = trim((string) ($datos['imagen_url'] ?? ''));

        if (hay_imagen_subida('imagen')) {
            if ($error = validar_imagen_subida($_FILES['imagen'])) {
                $v->agregarError('imagen', $error);
            }
        } elseif ($urlImagen !== '') {
            if (!filter_var($urlImagen, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $urlImagen) || strlen($urlImagen) > 500) {
                $v->agregarError('imagen', 'La URL de la imagen no es válida (debe empezar por http:// o https://).');
            }
            $imagenNueva = $urlImagen;
        } elseif (!$actual) {
            $v->agregarError('imagen', 'Agrega una imagen del producto.');
        }

        $v->verificar();

        if (hay_imagen_subida('imagen')) {
            $imagenNueva = guardar_imagen_subida($_FILES['imagen']);
        }

        $imagen = $imagenNueva ?? $actual['imagen'];
        $pdo = db();
        $pdo->beginTransaction();

        try {
            if ($actual) {
                consulta(
                    'UPDATE productos SET nombre = ?, descripcion = ?, precio = ?, categoria_id = ?, imagen = ?, activo = ?
                     WHERE id = ? AND tienda_id = ?',
                    [$nombre, $descripcion, $precio, $categoriaId, $imagen, (int) $activo, $actual['id'], $tiendaId]
                );
                $productoId = (int) $actual['id'];
            } else {
                consulta(
                    'INSERT INTO productos (tienda_id, categoria_id, nombre, descripcion, precio, imagen, activo)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [$tiendaId, $categoriaId, $nombre, $descripcion, $precio, $imagen, (int) $activo]
                );
                $productoId = (int) $pdo->lastInsertId();
            }

            sincronizar_variantes($productoId, $variantes);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            if ($imagenNueva !== null) {
                eliminar_imagen_local($imagenNueva);
            }
            throw $e;
        }

        if ($actual && $imagenNueva !== null && $actual['imagen'] !== $imagenNueva) {
            eliminar_imagen_local($actual['imagen']);
        }

        responder(['id' => $productoId], $actual ? 200 : 201);

    case 'estado-producto':
        exigir_metodo('POST');
        $producto = producto_de_la_tienda((int) (entrada()['id'] ?? 0), $tiendaId);
        $activo = !empty(entrada()['activo']);

        consulta('UPDATE productos SET activo = ? WHERE id = ?', [(int) $activo, $producto['id']]);
        responder(['id' => (int) $producto['id'], 'activo' => $activo]);

    case 'eliminar-producto':
        exigir_metodo('POST');
        $producto = producto_de_la_tienda((int) (entrada()['id'] ?? 0), $tiendaId);

        // Los pedidos conservan nombre, precio e imagen del producto (pedido_items),
        // por eso se puede borrar sin perder el historial de ventas.
        consulta('DELETE FROM productos WHERE id = ?', [$producto['id']]);

        $imagenEnUso = consulta('SELECT 1 FROM pedido_items WHERE imagen = ? LIMIT 1', [$producto['imagen']])->fetchColumn();
        if (!$imagenEnUso) {
            eliminar_imagen_local($producto['imagen']);
        }

        responder(['id' => (int) $producto['id']]);

    case 'ventas':
        exigir_metodo('GET');

        $estado = (string) ($_GET['estado'] ?? '');
        $filtro = in_array($estado, ESTADOS_ITEM, true) ? ' AND pi.estado = ?' : '';
        $parametros = $filtro ? [$tiendaId, $estado] : [$tiendaId];

        $pedidos = array_map('formatear_pedido', consulta(
            "SELECT pe.id, pe.codigo, pe.comprador_id, pe.creado_en, pe.nombre_envio, pe.email_contacto, pe.telefono,
                    pe.direccion, pe.ciudad, pe.departamento, pe.notas, pe.metodo_pago, pe.estado_pago,
                    pe.detalle_pago, pe.subtotal, pe.costo_envio, pe.total
             FROM pedidos pe
             WHERE EXISTS (SELECT 1 FROM pedido_items pi WHERE pi.pedido_id = pe.id AND pi.tienda_id = ?$filtro)
             ORDER BY pe.creado_en DESC, pe.id DESC
             LIMIT 100",
            $parametros
        )->fetchAll());

        $items = items_de_pedidos(array_column($pedidos, 'id'), $tiendaId);

        $resultado = array_map(function (array $pedido) use ($items) {
            $mios = $items[$pedido['id']] ?? [];
            $estado = $mios[0]['estado'] ?? 'nuevo';

            return [
                'id'             => $pedido['id'],
                'codigo'         => $pedido['codigo'],
                'creado_en'      => $pedido['creado_en'],
                'comprador'      => [
                    'nombre'       => $pedido['nombre_envio'],
                    'email'        => $pedido['email_contacto'],
                    'telefono'     => $pedido['telefono'],
                    'direccion'    => $pedido['direccion'],
                    'ciudad'       => $pedido['ciudad'],
                    'departamento' => $pedido['departamento'],
                    'notas'        => $pedido['notas'],
                ],
                'pago'           => ['metodo' => $pedido['metodo_pago'], 'estado' => $pedido['estado_pago'], 'detalle' => $pedido['detalle_pago']],
                'items'          => $mios,
                'total_tienda'   => array_sum(array_column($mios, 'subtotal')),
                'estado'         => $estado,
                'transiciones'   => TRANSICIONES[$estado] ?? [],
            ];
        }, $pedidos);

        responder($resultado);

    case 'estado-venta':
        exigir_metodo('POST');
        $datos = entrada();
        $pedidoId = (int) ($datos['pedido_id'] ?? 0);
        $nuevoEstado = (string) ($datos['estado'] ?? '');

        $pdo = db();
        $pdo->beginTransaction();

        try {
            $estadoActual = consulta(
                'SELECT estado FROM pedido_items WHERE pedido_id = ? AND tienda_id = ? ORDER BY id LIMIT 1 FOR UPDATE',
                [$pedidoId, $tiendaId]
            )->fetchColumn();

            if ($estadoActual === false) {
                throw new ErrorApi('No encontramos esa venta en tu tienda.', 404);
            }

            if (!in_array($nuevoEstado, TRANSICIONES[$estadoActual] ?? [], true)) {
                throw new ErrorApi("No se puede pasar de '$estadoActual' a '$nuevoEstado'.", 422);
            }

            if ($nuevoEstado === 'cancelado') {
                // Devolver las unidades al inventario.
                consulta(
                    "UPDATE producto_variantes v
                     JOIN pedido_items pi ON pi.variante_id = v.id
                     SET v.stock = v.stock + pi.cantidad
                     WHERE pi.pedido_id = ? AND pi.tienda_id = ? AND pi.estado <> 'cancelado'",
                    [$pedidoId, $tiendaId]
                );
            }

            consulta(
                'UPDATE pedido_items SET estado = ? WHERE pedido_id = ? AND tienda_id = ?',
                [$nuevoEstado, $pedidoId, $tiendaId]
            );

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        notificar_cambio_estado($pedidoId, $tiendaId, $nuevoEstado);

        responder(['pedido_id' => $pedidoId, 'estado' => $nuevoEstado, 'transiciones' => TRANSICIONES[$nuevoEstado]]);

    case 'tienda':
        exigir_metodo('GET');
        responder(consulta('SELECT id, nombre, ciudad, descripcion FROM tiendas WHERE id = ?', [$tiendaId])->fetch());

    case 'guardar-tienda':
        exigir_metodo('POST');
        $v = new Validador(entrada());
        $nombre = $v->texto('nombre', 'El nombre de la tienda', 3, 120);
        $ciudad = $v->texto('ciudad', 'La ciudad', 2, 80);
        $descripcion = $v->texto('descripcion', 'La descripción', 0, 500, requerido: false);

        if ($nombre && consulta('SELECT 1 FROM tiendas WHERE nombre = ? AND id <> ?', [$nombre, $tiendaId])->fetchColumn()) {
            $v->agregarError('nombre', 'Ya existe otra tienda con ese nombre.');
        }

        $v->verificar();

        consulta('UPDATE tiendas SET nombre = ?, ciudad = ?, descripcion = ? WHERE id = ?', [$nombre, $ciudad, $descripcion, $tiendaId]);
        responder(['id' => $tiendaId, 'nombre' => $nombre, 'ciudad' => $ciudad, 'descripcion' => $descripcion]);

    default:
        throw new ErrorApi('Acción no encontrada.', 404);
}

/** Producto de la tienda del vendedor (404 si no existe o es de otra tienda). */
function producto_de_la_tienda(int $productoId, int $tiendaId): array
{
    $producto = consulta('SELECT * FROM productos WHERE id = ? AND tienda_id = ?', [$productoId, $tiendaId])->fetch();

    if (!$producto) {
        throw new ErrorApi('No encontramos ese producto en tu tienda.', 404);
    }

    return $producto;
}

/**
 * Las variantes llegan como JSON: [{"talla":"M","color":"Negro","stock":5}, ...]
 */
function validar_variantes(Validador $v, mixed $json): array
{
    $lista = is_array($json) ? $json : json_decode((string) $json, true);

    if (!is_array($lista) || $lista === []) {
        $v->agregarError('variantes', 'Agrega al menos una talla y un color.');
        return [];
    }

    $variantes = [];

    foreach ($lista as $item) {
        $talla = (string) ($item['talla'] ?? '');
        $color = (string) ($item['color'] ?? '');
        $stock = filter_var($item['stock'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 9999]]);

        if (!in_array($talla, TALLAS, true) || !in_array($color, COLORES, true)) {
            $v->agregarError('variantes', 'Hay una talla o un color no válido.');
            return [];
        }

        if ($stock === false) {
            $v->agregarError('variantes', "El stock de $talla / $color debe ser un número entre 0 y 9999.");
            return [];
        }

        $variantes["$talla|$color"] = ['talla' => $talla, 'color' => $color, 'stock' => $stock];
    }

    if (count($variantes) > 150) {
        $v->agregarError('variantes', 'Demasiadas combinaciones de talla y color.');
    }

    return array_values($variantes);
}

/** Inserta, actualiza o elimina variantes para que coincidan con la lista enviada. */
function sincronizar_variantes(int $productoId, array $variantes): void
{
    $existentes = [];

    foreach (consulta('SELECT id, talla, color FROM producto_variantes WHERE producto_id = ?', [$productoId])->fetchAll() as $fila) {
        $existentes["{$fila['talla']}|{$fila['color']}"] = (int) $fila['id'];
    }

    foreach ($variantes as $variante) {
        $clave = "{$variante['talla']}|{$variante['color']}";

        if (isset($existentes[$clave])) {
            consulta('UPDATE producto_variantes SET stock = ? WHERE id = ?', [$variante['stock'], $existentes[$clave]]);
            unset($existentes[$clave]);
        } else {
            consulta(
                'INSERT INTO producto_variantes (producto_id, talla, color, stock) VALUES (?, ?, ?, ?)',
                [$productoId, $variante['talla'], $variante['color'], $variante['stock']]
            );
        }
    }

    if ($existentes) {
        $marcadores = implode(',', array_fill(0, count($existentes), '?'));
        consulta("DELETE FROM producto_variantes WHERE id IN ($marcadores)", array_values($existentes));
    }
}
