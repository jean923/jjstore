<?php
/**
 * API de compras (comprador).
 *
 *   POST pedidos.php?accion=cotizar              -> precios y stock actuales del carrito
 *   POST pedidos.php?accion=crear                -> pagar (simulado) y crear el pedido
 *   GET  pedidos.php?accion=mis-pedidos          -> historial del comprador
 *   GET  pedidos.php?accion=detalle&codigo=X     -> un pedido
 *   GET  pedidos.php?accion=correo&codigo=X      -> HTML del correo de confirmación
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';
require RAIZ . '/app/notificaciones.php';

switch (accion()) {
    case 'cotizar':
        exigir_metodo('POST');
        $cotizacion = cotizar(normalizar_items(entrada()['items'] ?? null));
        responder(sin_datos_internos($cotizacion));

    case 'crear':
        exigir_metodo('POST');
        $comprador = requerir_usuario();
        $datos = entrada();

        $cantidades = normalizar_items($datos['items'] ?? null);
        $envio = validar_envio($datos['envio'] ?? null);
        $pago = validar_datos_pago($datos['pago'] ?? null);

        $pedido = crear_pedido($comprador, $cantidades, $envio, $pago);
        $estadoCorreo = notificar_pedido_creado($pedido['id']);

        responder(['codigo' => $pedido['codigo'], 'correo' => $estadoCorreo], 201);

    case 'mis-pedidos':
        exigir_metodo('GET');
        $comprador = requerir_usuario();

        $pedidos = array_map('formatear_pedido', consulta(
            'SELECT id, codigo, comprador_id, metodo_pago, estado_pago, detalle_pago, subtotal, costo_envio, total,
                    ciudad, creado_en
             FROM pedidos
             WHERE comprador_id = ?
             ORDER BY creado_en DESC, id DESC
             LIMIT 100',
            [$comprador['id']]
        )->fetchAll());

        $items = items_de_pedidos(array_column($pedidos, 'id'));

        foreach ($pedidos as &$pedido) {
            $pedido['items'] = $items[$pedido['id']] ?? [];
        }

        responder($pedidos);

    case 'detalle':
        exigir_metodo('GET');
        $comprador = requerir_usuario();
        $pedido = pedido_del_comprador((string) ($_GET['codigo'] ?? ''), (int) $comprador['id'])
            ?? throw new ErrorApi('No encontramos ese pedido en tu cuenta.', 404);

        $pedido['correo'] = consulta(
            "SELECT estado, destinatario, creado_en FROM correos_enviados
             WHERE pedido_id = ? AND tipo = 'compra' ORDER BY id DESC LIMIT 1",
            [$pedido['id']]
        )->fetch() ?: null;

        responder($pedido);

    case 'correo':
        exigir_metodo('GET');
        $comprador = requerir_usuario();
        $pedido = pedido_del_comprador((string) ($_GET['codigo'] ?? ''), (int) $comprador['id'])
            ?? throw new ErrorApi('No encontramos ese pedido en tu cuenta.', 404);

        $html = consulta(
            "SELECT html FROM correos_enviados WHERE pedido_id = ? AND tipo = 'compra' ORDER BY id DESC LIMIT 1",
            [$pedido['id']]
        )->fetchColumn();

        if ($html === false) {
            throw new ErrorApi('Este pedido no tiene correo registrado.', 404);
        }

        // Se muestra como página: sin scripts y solo con imágenes/estilos.
        header('Content-Type: text/html; charset=utf-8');
        header("Content-Security-Policy: default-src 'none'; img-src https: http: data:; style-src 'unsafe-inline'");
        header('X-Content-Type-Options: nosniff');
        echo $html;
        exit;

    default:
        throw new ErrorApi('Acción no encontrada.', 404);
}

/** Quita del resultado los datos que el navegador no necesita. */
function sin_datos_internos(array $cotizacion): array
{
    $cotizacion['lineas'] = array_map(function (array $linea) {
        unset($linea['tienda_usuario_id']);
        return $linea;
    }, $cotizacion['lineas']);

    return $cotizacion;
}
