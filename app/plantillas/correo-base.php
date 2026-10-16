<?php
/**
 * Estructura común de todos los correos.
 * Variables: $titulo, $preheader, $contenido (HTML ya generado)
 */
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($titulo) ?></title>
</head>
<body style="margin:0;padding:0;background:#efeeea;font-family:Arial,Helvetica,sans-serif;color:#151515;">
<span style="display:none;max-height:0;overflow:hidden;opacity:0;"><?= e($preheader) ?></span>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#efeeea;">
    <tr>
        <td align="center" style="padding:28px 12px;">
            <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
                   style="max-width:600px;background:#ffffff;border:1px solid #dddad3;">
                <tr>
                    <td style="background:#0c0c0c;padding:26px 32px;">
                        <div style="font-size:26px;font-weight:800;letter-spacing:-1px;color:#f0efed;">
                            JJ<span style="color:#c9c5bd;">STORE.</span>
                        </div>
                        <div style="font-size:9px;letter-spacing:3px;color:#999999;margin-top:6px;">
                            STREETWEAR · MARKETPLACE
                        </div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:32px;">
                        <?= $contenido ?>
                    </td>
                </tr>
                <tr>
                    <td style="padding:22px 32px;background:#f7f6f3;border-top:1px solid #e6e3dc;font-size:11px;line-height:1.7;color:#77736d;">
                        Este correo fue generado por JJ STORE, una tienda de demostración
                        (proyecto de grado). No se realizó ningún cobro real.<br>
                        Medellín, Colombia · © <?= date('Y') ?> JJ STORE
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
