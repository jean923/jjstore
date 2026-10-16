<?php
/**
 * Correos del flujo de compra:
 *   - compra: confirmación al comprador.
 *   - venta:  aviso a cada tienda que vendió algo en el pedido.
 *   - estado: aviso al comprador cuando una tienda actualiza su envío.
 */

declare(strict_types=1);

require_once RAIZ . '/app/correo.php';
require_once RAIZ . '/app/pedidos.php';

const NOMBRES_ESTADO = [
    'nuevo'      => 'Nuevo',
    'preparando' => 'Preparando',
    'enviado'    => 'Enviado',
    'entregado'  => 'Entregado',
    'cancelado'  => 'Cancelado',
];

/** Envía la confirmación al comprador y el aviso a los vendedores. Devuelve el estado del correo del comprador. */
function notificar_pedido_creado(int $pedidoId): string
{
    $pedido = formatear_pedido(consulta('SELECT * FROM pedidos WHERE id = ?', [$pedidoId])->fetch());
    $items = items_de_pedidos([$pedidoId])[$pedidoId] ?? [];
    $urlPedido = url_base() . '/pedido.html?codigo=' . rawurlencode($pedido['codigo']);

    $html = plantilla('correo-base', [
        'titulo'    => 'Confirmación de compra',
        'preheader' => "Recibimos tu pedido {$pedido['codigo']} por " . pesos($pedido['total']),
        'contenido' => plantilla('correo-compra', compact('pedido', 'items', 'urlPedido')),
    ]);

    $estado = enviar_correo(
        'compra',
        $pedido['email_contacto'],
        $pedido['nombre_envio'],
        "Confirmación de tu compra {$pedido['codigo']} · JJ STORE",
        $html,
        $pedidoId
    );

    notificar_vendedores($pedido, $items);

    return $estado;
}

function notificar_vendedores(array $pedido, array $items): void
{
    $porTienda = [];

    foreach ($items as $item) {
        $porTienda[$item['tienda_id']][] = $item;
    }

    $marcadores = implode(',', array_fill(0, count($porTienda), '?'));
    $tiendas = consulta(
        "SELECT t.id, t.nombre, u.nombre AS vendedor, u.email
         FROM tiendas t JOIN usuarios u ON u.id = t.usuario_id
         WHERE t.id IN ($marcadores)",
        array_keys($porTienda)
    )->fetchAll();

    foreach ($tiendas as $tienda) {
        $itemsTienda = $porTienda[(int) $tienda['id']];
        $totalTienda = array_sum(array_column($itemsTienda, 'subtotal'));

        $html = plantilla('correo-base', [
            'titulo'    => 'Nueva venta',
            'preheader' => "Vendiste " . pesos($totalTienda) . " en el pedido {$pedido['codigo']}",
            'contenido' => plantilla('correo-venta', [
                'pedido'      => $pedido,
                'tienda'      => $tienda,
                'items'       => $itemsTienda,
                'totalTienda' => $totalTienda,
                'urlPanel'    => url_base() . '/vendedor.html#ventas',
            ]),
        ]);

        enviar_correo('venta', $tienda['email'], $tienda['vendedor'], "¡Nueva venta! Pedido {$pedido['codigo']} · JJ STORE", $html, $pedido['id']);
    }
}

function notificar_cambio_estado(int $pedidoId, int $tiendaId, string $estado): void
{
    $pedido = formatear_pedido(consulta('SELECT * FROM pedidos WHERE id = ?', [$pedidoId])->fetch());
    $items = items_de_pedidos([$pedidoId], $tiendaId)[$pedidoId] ?? [];
    $tienda = $items[0]['tienda'] ?? 'La tienda';

    $html = plantilla('correo-base', [
        'titulo'    => 'Actualización de tu pedido',
        'preheader' => "Tu pedido {$pedido['codigo']} ahora está: " . NOMBRES_ESTADO[$estado],
        'contenido' => plantilla('correo-estado', [
            'pedido'    => $pedido,
            'items'     => $items,
            'tienda'    => $tienda,
            'estado'    => $estado,
            'urlPedido' => url_base() . '/mis-pedidos.html',
        ]),
    ]);

    enviar_correo(
        'estado',
        $pedido['email_contacto'],
        $pedido['nombre_envio'],
        "Tu pedido {$pedido['codigo']}: " . mb_strtolower(NOMBRES_ESTADO[$estado]) . ' · JJ STORE',
        $html,
        $pedidoId
    );
}
