<?php
/**
 * Tabla de productos reutilizada en todos los correos.
 * Variables: $items, $mostrarTienda (bool)
 */
?>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-top:1px solid #e6e3dc;">
    <?php foreach ($items as $item): ?>
        <tr>
            <td width="72" style="padding:14px 0;border-bottom:1px solid #e6e3dc;vertical-align:top;">
                <img src="<?= e(url_imagen($item['imagen'])) ?>" alt="" width="60" height="60"
                     style="display:block;width:60px;height:60px;object-fit:cover;background:#eeeeee;border:0;">
            </td>
            <td style="padding:14px 10px;border-bottom:1px solid #e6e3dc;vertical-align:top;font-size:13px;line-height:1.6;">
                <strong style="color:#151515;"><?= e($item['nombre']) ?></strong><br>
                <span style="color:#77736d;">
                    Talla <?= e($item['talla']) ?> · <?= e($item['color']) ?> · Cant. <?= (int) $item['cantidad'] ?>
                </span>
                <?php if (!empty($mostrarTienda)): ?>
                    <br><span style="color:#9a958d;font-size:11px;">Vendido por <?= e($item['tienda']) ?></span>
                <?php endif; ?>
            </td>
            <td align="right" style="padding:14px 0;border-bottom:1px solid #e6e3dc;vertical-align:top;font-size:13px;font-weight:bold;white-space:nowrap;">
                <?= pesos($item['subtotal']) ?>
            </td>
        </tr>
    <?php endforeach; ?>
</table>
