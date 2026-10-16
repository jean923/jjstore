<?php
/**
 * Aviso de nueva venta para el vendedor.
 * Variables: $pedido, $tienda, $items, $totalTienda, $urlPanel
 */
$unidades = array_sum(array_column($items, 'cantidad'));
?>
<p style="margin:0 0 10px;font-size:10px;letter-spacing:3px;color:#8a857d;font-weight:bold;">NUEVA VENTA · <?= e(mb_strtoupper($tienda['nombre'])) ?></p>
<h1 style="margin:0 0 14px;font-size:26px;line-height:1.25;color:#0c0c0c;">¡Vendiste <?= pesos($totalTienda) ?>!</h1>
<p style="margin:0 0 24px;font-size:14px;line-height:1.7;color:#4a4742;">
    <?= e($pedido['nombre_envio']) ?> compró <?= $unidades ?> <?= $unidades === 1 ? 'unidad' : 'unidades' ?>
    en tu tienda (pedido <strong><?= e($pedido['codigo']) ?></strong>). Prepara los productos y actualiza
    el estado del envío desde tu panel.
</p>

<?= plantilla('correo-items', ['items' => $items, 'mostrarTienda' => false]) ?>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0 26px;">
    <tr>
        <td style="font-size:15px;font-weight:bold;color:#0c0c0c;">Total de tu venta</td>
        <td align="right" style="font-size:15px;font-weight:bold;color:#0c0c0c;"><?= pesos($totalTienda) ?></td>
    </tr>
</table>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f7f6f3;border:1px solid #e6e3dc;margin-bottom:26px;">
    <tr>
        <td style="padding:16px 18px;font-size:12px;line-height:1.8;color:#4a4742;">
            <strong style="color:#151515;">Enviar a:</strong> <?= e($pedido['nombre_envio']) ?><br>
            <strong style="color:#151515;">Teléfono:</strong> <?= e($pedido['telefono']) ?><br>
            <strong style="color:#151515;">Dirección:</strong> <?= e($pedido['direccion']) ?>,
            <?= e($pedido['ciudad']) ?>, <?= e($pedido['departamento']) ?><br>
            <?php if ($pedido['notas']): ?>
                <strong style="color:#151515;">Indicaciones:</strong> <?= e($pedido['notas']) ?><br>
            <?php endif; ?>
            <strong style="color:#151515;">Pago:</strong> <?= e($pedido['detalle_pago']) ?>
        </td>
    </tr>
</table>

<table role="presentation" cellpadding="0" cellspacing="0">
    <tr>
        <td style="background:#0c0c0c;">
            <a href="<?= e($urlPanel) ?>"
               style="display:inline-block;padding:14px 24px;font-size:12px;font-weight:bold;letter-spacing:1px;color:#f0efed;text-decoration:none;">
                IR A MI PANEL DE VENTAS →
            </a>
        </td>
    </tr>
</table>
