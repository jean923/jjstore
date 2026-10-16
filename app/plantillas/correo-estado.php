<?php
/**
 * Actualización del estado del envío para el comprador.
 * Variables: $pedido, $items, $tienda, $estado, $urlPedido
 */
$mensajes = [
    'preparando' => ['Estamos preparando tu pedido', "$tienda ya está alistando tus productos."],
    'enviado'    => ['¡Tu pedido va en camino!', "$tienda despachó tus productos. Muy pronto llegarán a tu puerta."],
    'entregado'  => ['Pedido entregado', "Tus productos de $tienda fueron entregados. ¡Esperamos que los disfrutes!"],
    'cancelado'  => ['Productos cancelados', "$tienda canceló estos productos. Si ya pagaste, el reembolso (simulado) se verá reflejado en tu medio de pago."],
];
[$titulo, $texto] = $mensajes[$estado] ?? ['Tu pedido cambió de estado', ''];
?>
<p style="margin:0 0 10px;font-size:10px;letter-spacing:3px;color:#8a857d;font-weight:bold;">
    PEDIDO <?= e($pedido['codigo']) ?> · <?= e(mb_strtoupper(NOMBRES_ESTADO[$estado])) ?>
</p>
<h1 style="margin:0 0 14px;font-size:26px;line-height:1.25;color:#0c0c0c;"><?= e($titulo) ?></h1>
<p style="margin:0 0 24px;font-size:14px;line-height:1.7;color:#4a4742;"><?= e($texto) ?></p>

<?= plantilla('correo-items', ['items' => $items, 'mostrarTienda' => false]) ?>

<table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:28px;">
    <tr>
        <td style="background:#0c0c0c;">
            <a href="<?= e($urlPedido) ?>"
               style="display:inline-block;padding:14px 24px;font-size:12px;font-weight:bold;letter-spacing:1px;color:#f0efed;text-decoration:none;">
                VER MIS PEDIDOS →
            </a>
        </td>
    </tr>
</table>
