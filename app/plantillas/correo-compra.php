<?php
/**
 * Confirmación de compra para el comprador.
 * Variables: $pedido, $items, $urlPedido
 */
$primerNombre = explode(' ', trim($pedido['nombre_envio']))[0];
?>
<p style="margin:0 0 10px;font-size:10px;letter-spacing:3px;color:#8a857d;font-weight:bold;">CONFIRMACIÓN DE COMPRA</p>
<h1 style="margin:0 0 14px;font-size:26px;line-height:1.25;color:#0c0c0c;">
    ¡Gracias por tu compra, <?= e($primerNombre) ?>!
</h1>
<p style="margin:0 0 24px;font-size:14px;line-height:1.7;color:#4a4742;">
    Recibimos tu pedido y ya avisamos a las tiendas para que lo preparen.
    Te escribiremos cuando vaya en camino.
</p>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f7f6f3;border:1px solid #e6e3dc;margin-bottom:26px;">
    <tr>
        <td style="padding:16px 18px;font-size:12px;line-height:1.8;color:#4a4742;">
            <strong style="color:#151515;">Pedido:</strong> <?= e($pedido['codigo']) ?><br>
            <strong style="color:#151515;">Fecha:</strong> <?= e(date('d/m/Y h:i a', strtotime($pedido['creado_en']))) ?><br>
            <strong style="color:#151515;">Pago:</strong> <?= e($pedido['detalle_pago']) ?>
            (<?= $pedido['estado_pago'] === 'aprobado' ? 'aprobado' : 'se paga al recibir' ?>)<br>
            <strong style="color:#151515;">Referencia:</strong> <?= e($pedido['referencia_pago']) ?>
        </td>
    </tr>
</table>

<?= plantilla('correo-items', ['items' => $items, 'mostrarTienda' => true]) ?>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0 28px;font-size:13px;color:#4a4742;">
    <tr>
        <td style="padding:4px 0;">Subtotal</td>
        <td align="right" style="padding:4px 0;"><?= pesos($pedido['subtotal']) ?></td>
    </tr>
    <tr>
        <td style="padding:4px 0;">Envío</td>
        <td align="right" style="padding:4px 0;"><?= $pedido['costo_envio'] > 0 ? pesos($pedido['costo_envio']) : 'Gratis' ?></td>
    </tr>
    <tr>
        <td style="padding:10px 0 0;font-size:16px;font-weight:bold;color:#0c0c0c;border-top:1px solid #e6e3dc;">Total</td>
        <td align="right" style="padding:10px 0 0;font-size:16px;font-weight:bold;color:#0c0c0c;border-top:1px solid #e6e3dc;"><?= pesos($pedido['total']) ?></td>
    </tr>
</table>

<p style="margin:0 0 6px;font-size:10px;letter-spacing:2px;color:#8a857d;font-weight:bold;">ENVIAREMOS A</p>
<p style="margin:0 0 28px;font-size:13px;line-height:1.7;color:#4a4742;">
    <?= e($pedido['nombre_envio']) ?> · <?= e($pedido['telefono']) ?><br>
    <?= e($pedido['direccion']) ?><br>
    <?= e($pedido['ciudad']) ?>, <?= e($pedido['departamento']) ?>
    <?php if ($pedido['notas']): ?>
        <br><em>“<?= e($pedido['notas']) ?>”</em>
    <?php endif; ?>
</p>

<table role="presentation" cellpadding="0" cellspacing="0">
    <tr>
        <td style="background:#0c0c0c;">
            <a href="<?= e($urlPedido) ?>"
               style="display:inline-block;padding:14px 24px;font-size:12px;font-weight:bold;letter-spacing:1px;color:#f0efed;text-decoration:none;">
                VER MI PEDIDO →
            </a>
        </td>
    </tr>
</table>
