<?php
/**
 * Lógica de pedidos: cotización del carrito, creación de la compra y consultas.
 */

declare(strict_types=1);

require_once RAIZ . '/app/pagos.php';

const ESTADOS_ITEM = ['nuevo', 'preparando', 'enviado', 'entregado', 'cancelado'];

/** Cambios de estado que puede hacer un vendedor desde cada estado. */
const TRANSICIONES = [
    'nuevo'      => ['preparando', 'enviado', 'cancelado'],
    'preparando' => ['enviado', 'cancelado'],
    'enviado'    => ['entregado'],
    'entregado'  => [],
    'cancelado'  => [],
];

const MAX_UNIDADES_POR_ITEM = 20;

/**
 * Convierte el carrito enviado por el navegador en [variante_id => cantidad].
 * El navegador solo indica QUÉ quiere comprar; precios y stock se leen de la BD.
 */
function normalizar_items(mixed $items): array
{
    if (!is_array($items) || $items === []) {
        throw new ErrorApi('Tu carrito está vacío.', 422);
    }

    $cantidades = [];

    foreach ($items as $item) {
        $varianteId = filter_var($item['variante_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $cantidad = filter_var($item['cantidad'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($varianteId === false || $cantidad === false) {
            throw new ErrorApi('El carrito contiene datos inválidos. Vacíalo e intenta de nuevo.', 422);
        }

        $cantidades[$varianteId] = min(($cantidades[$varianteId] ?? 0) + $cantidad, MAX_UNIDADES_POR_ITEM);
    }

    if (count($cantidades) > 50) {
        throw new ErrorApi('El carrito tiene demasiados productos.', 422);
    }

    return $cantidades;
}

/**
 * Calcula precios, envío y disponibilidad con los datos ACTUALES de la base de datos.
 *
 * Con $bloquear = true (dentro de una transacción) las filas de inventario quedan
 * bloqueadas con SELECT ... FOR UPDATE hasta el commit: dos compradores no pueden
 * llevarse la última unidad al mismo tiempo. Se bloquean en orden de id para evitar
 * bloqueos mutuos (deadlocks).
 */
function cotizar(array $cantidades, bool $bloquear = false): array
{
    $ids = array_keys($cantidades);
    sort($ids);
    $marcadores = implode(',', array_fill(0, count($ids), '?'));

    $filas = consulta(
        "SELECT v.id AS variante_id, v.talla, v.color, v.stock,
                p.id AS producto_id, p.nombre, p.imagen, p.precio, p.activo,
                t.id AS tienda_id, t.nombre AS tienda, t.usuario_id AS tienda_usuario_id
         FROM producto_variantes v
         JOIN productos p ON p.id = v.producto_id
         JOIN tiendas t   ON t.id = p.tienda_id
         WHERE v.id IN ($marcadores)
         ORDER BY v.id" . ($bloquear ? ' FOR UPDATE' : ''),
        $ids
    )->fetchAll();

    $porVariante = array_column($filas, null, 'variante_id');
    $lineas = [];
    $problemas = [];
    $subtotal = 0.0;

    foreach ($cantidades as $varianteId => $cantidad) {
        $fila = $porVariante[$varianteId] ?? null;

        if (!$fila || !$fila['activo']) {
            $problemas[] = ['variante_id' => $varianteId, 'mensaje' => 'Un producto de tu carrito ya no está disponible.'];
            continue;
        }

        $stock = (int) $fila['stock'];
        $precio = (float) $fila['precio'];
        $descripcion = "{$fila['nombre']} ({$fila['talla']} / {$fila['color']})";

        if ($stock === 0) {
            $problemas[] = ['variante_id' => $varianteId, 'mensaje' => "$descripcion está agotado."];
        } elseif ($stock < $cantidad) {
            $problemas[] = ['variante_id' => $varianteId, 'mensaje' => "Solo quedan $stock unidades de $descripcion."];
        }

        $lineas[] = [
            'variante_id'       => (int) $varianteId,
            'producto_id'       => (int) $fila['producto_id'],
            'nombre'            => $fila['nombre'],
            'imagen'            => $fila['imagen'],
            'talla'             => $fila['talla'],
            'color'             => $fila['color'],
            'tienda_id'         => (int) $fila['tienda_id'],
            'tienda'            => $fila['tienda'],
            'tienda_usuario_id' => (int) $fila['tienda_usuario_id'],
            'precio'            => $precio,
            'cantidad'          => $cantidad,
            'stock'             => $stock,
            'subtotal'          => $precio * $cantidad,
        ];

        $subtotal += $precio * $cantidad;
    }

    $envio = ($subtotal === 0.0 || $subtotal >= ENVIO_GRATIS_DESDE) ? 0 : COSTO_ENVIO;

    return [
        'lineas'             => $lineas,
        'problemas'          => $problemas,
        'subtotal'           => $subtotal,
        'envio'              => $envio,
        'total'              => $subtotal + $envio,
        'envio_gratis_desde' => ENVIO_GRATIS_DESDE,
    ];
}

/** Valida los datos de envío del formulario de checkout. */
function validar_envio(mixed $envio): array
{
    $envio = is_array($envio) ? $envio : [];
    $v = new Validador($envio);

    $datos = [
        'nombre'       => $v->texto('nombre', 'El nombre de quien recibe', 3, 120),
        'email'        => $v->email('email'),
        'telefono'     => $v->telefono('telefono'),
        'direccion'    => $v->texto('direccion', 'La dirección', 5, 200),
        'ciudad'       => $v->texto('ciudad', 'La ciudad', 2, 80),
        'departamento' => $v->texto('departamento', 'El departamento', 2, 80),
        'notas'        => $v->texto('notas', 'Las indicaciones', 0, 300, requerido: false),
    ];

    $v->verificar();

    return $datos;
}

/**
 * Crea el pedido dentro de una transacción:
 *   1. Bloquea y revisa el inventario.
 *   2. Procesa el pago simulado (si se rechaza, no se guarda nada).
 *   3. Descuenta el stock y guarda el pedido con sus ítems.
 * Los correos se envían DESPUÉS del commit: un fallo de correo no anula la compra.
 */
function crear_pedido(array $comprador, array $cantidades, array $envio, array $pago): array
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $cotizacion = cotizar($cantidades, bloquear: true);

        if ($cotizacion['problemas']) {
            throw new ErrorApi(
                'Algunos productos de tu carrito cambiaron. Revisa el resumen e intenta de nuevo.',
                409,
                ['carrito' => array_column($cotizacion['problemas'], 'mensaje')]
            );
        }

        foreach ($cotizacion['lineas'] as $linea) {
            if ($linea['tienda_usuario_id'] === (int) $comprador['id']) {
                throw new ErrorApi("No puedes comprar productos de tu propia tienda ({$linea['nombre']}).", 422);
            }
        }

        $resultadoPago = procesar_pago_simulado($pago);

        $descontar = $pdo->prepare('UPDATE producto_variantes SET stock = stock - ? WHERE id = ? AND stock >= ?');

        foreach ($cotizacion['lineas'] as $linea) {
            $descontar->execute([$linea['cantidad'], $linea['variante_id'], $linea['cantidad']]);

            if ($descontar->rowCount() !== 1) {
                throw new ErrorApi('Uno de los productos se agotó mientras comprabas. Intenta de nuevo.', 409);
            }
        }

        $codigo = generar_codigo_pedido();

        consulta(
            'INSERT INTO pedidos (codigo, comprador_id, nombre_envio, email_contacto, telefono, direccion, ciudad,
                                  departamento, notas, metodo_pago, estado_pago, detalle_pago, referencia_pago,
                                  subtotal, costo_envio, total)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $codigo, $comprador['id'], $envio['nombre'], $envio['email'], $envio['telefono'], $envio['direccion'],
                $envio['ciudad'], $envio['departamento'], $envio['notas'], $pago['metodo'],
                $resultadoPago['estado_pago'], $resultadoPago['detalle'], $resultadoPago['referencia'],
                $cotizacion['subtotal'], $cotizacion['envio'], $cotizacion['total'],
            ]
        );

        $pedidoId = (int) $pdo->lastInsertId();

        $insertarItem = $pdo->prepare(
            'INSERT INTO pedido_items (pedido_id, tienda_id, producto_id, variante_id, nombre_producto, imagen,
                                       talla, color, precio_unitario, cantidad, subtotal)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        foreach ($cotizacion['lineas'] as $linea) {
            $insertarItem->execute([
                $pedidoId, $linea['tienda_id'], $linea['producto_id'], $linea['variante_id'], $linea['nombre'],
                $linea['imagen'], $linea['talla'], $linea['color'], $linea['precio'], $linea['cantidad'],
                $linea['subtotal'],
            ]);
        }

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return ['id' => $pedidoId, 'codigo' => $codigo];
}

/** Código corto y legible, p. ej. JJ-7KQ2MX (sin 0/O ni 1/I para evitar confusiones). */
function generar_codigo_pedido(): string
{
    $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    do {
        $codigo = 'JJ-';
        for ($i = 0; $i < 6; $i++) {
            $codigo .= $alfabeto[random_int(0, strlen($alfabeto) - 1)];
        }
    } while (consulta('SELECT 1 FROM pedidos WHERE codigo = ?', [$codigo])->fetchColumn());

    return $codigo;
}

/** Pedido completo de un comprador (null si no existe o no es suyo). */
function pedido_del_comprador(string $codigo, int $compradorId): ?array
{
    $pedido = consulta('SELECT * FROM pedidos WHERE codigo = ? AND comprador_id = ?', [$codigo, $compradorId])->fetch();

    if (!$pedido) {
        return null;
    }

    $pedido = formatear_pedido($pedido);
    $pedido['items'] = items_de_pedidos([$pedido['id']])[$pedido['id']] ?? [];

    return $pedido;
}

/**
 * Ítems de varios pedidos en una sola consulta, agrupados por pedido.
 * Si se indica $tiendaId solo devuelve los ítems de esa tienda.
 */
function items_de_pedidos(array $pedidoIds, ?int $tiendaId = null): array
{
    if (!$pedidoIds) {
        return [];
    }

    $marcadores = implode(',', array_fill(0, count($pedidoIds), '?'));
    $parametros = array_values($pedidoIds);
    $filtroTienda = '';

    if ($tiendaId !== null) {
        $filtroTienda = ' AND pi.tienda_id = ?';
        $parametros[] = $tiendaId;
    }

    $filas = consulta(
        "SELECT pi.*, t.nombre AS tienda
         FROM pedido_items pi
         JOIN tiendas t ON t.id = pi.tienda_id
         WHERE pi.pedido_id IN ($marcadores)$filtroTienda
         ORDER BY pi.pedido_id, pi.tienda_id, pi.id",
        $parametros
    )->fetchAll();

    $agrupados = [];

    foreach ($filas as $fila) {
        $agrupados[(int) $fila['pedido_id']][] = [
            'id'              => (int) $fila['id'],
            'producto_id'     => $fila['producto_id'] !== null ? (int) $fila['producto_id'] : null,
            'tienda_id'       => (int) $fila['tienda_id'],
            'tienda'          => $fila['tienda'],
            'nombre'          => $fila['nombre_producto'],
            'imagen'          => $fila['imagen'],
            'talla'           => $fila['talla'],
            'color'           => $fila['color'],
            'precio_unitario' => (float) $fila['precio_unitario'],
            'cantidad'        => (int) $fila['cantidad'],
            'subtotal'        => (float) $fila['subtotal'],
            'estado'          => $fila['estado'],
        ];
    }

    return $agrupados;
}

function formatear_pedido(array $pedido): array
{
    foreach (['id', 'comprador_id'] as $campo) {
        $pedido[$campo] = (int) $pedido[$campo];
    }

    foreach (['subtotal', 'costo_envio', 'total'] as $campo) {
        $pedido[$campo] = (float) $pedido[$campo];
    }

    return $pedido;
}
