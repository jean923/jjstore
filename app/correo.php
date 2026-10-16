<?php
/**
 * Envío de correos.
 *
 * MAIL_DRIVER en .env:
 *   archivo -> (por defecto) no sale a internet: el correo se guarda en la tabla
 *              correos_enviados y se puede ver desde la página del pedido.
 *   smtp    -> se envía de verdad con PHPMailer (Gmail, Outlook, Mailtrap...).
 *
 * En ambos casos queda registro en correos_enviados.
 */

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;

/** Escapa texto para insertarlo en HTML. */
function e(mixed $texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

/** 150000 -> "$150.000" */
function pesos(float|int|string $valor): string
{
    return '$' . number_format((float) $valor, 0, ',', '.');
}

/** Renderiza una plantilla de app/plantillas con las variables indicadas. */
function plantilla(string $nombre, array $variables = []): string
{
    extract($variables, EXTR_SKIP);
    ob_start();
    require RAIZ . "/app/plantillas/$nombre.php";

    return (string) ob_get_clean();
}

/** Devuelve el estado final: 'enviado', 'simulado' o 'error'. */
function enviar_correo(string $tipo, string $para, string $nombre, string $asunto, string $html, ?int $pedidoId = null): string
{
    $driver = strtolower(config('MAIL_DRIVER', 'archivo'));
    $estado = 'simulado';
    $error = null;

    if ($driver === 'smtp') {
        try {
            enviar_por_smtp($para, $nombre, $asunto, $html);
            $estado = 'enviado';
        } catch (Throwable $e) {
            $estado = 'error';
            $error = mb_substr($e->getMessage(), 0, 500);
            error_log("[correo] No se pudo enviar '$asunto' a $para: " . $e->getMessage());
        }
    }

    consulta(
        'INSERT INTO correos_enviados (pedido_id, tipo, destinatario, asunto, html, estado, error)
         VALUES (?, ?, ?, ?, ?, ?, ?)',
        [$pedidoId, $tipo, $para, $asunto, $html, $estado, $error]
    );

    return $estado;
}

function enviar_por_smtp(string $para, string $nombre, string $asunto, string $html): void
{
    if (!class_exists(PHPMailer::class)) {
        throw new RuntimeException('PHPMailer no está instalado. Ejecuta "composer install" en la raíz del proyecto.');
    }

    $correo = new PHPMailer(true);
    $correo->isSMTP();
    $correo->Host = (string) config('MAIL_HOST', 'smtp.gmail.com');
    $correo->Port = (int) config('MAIL_PORT', '587');
    $correo->SMTPAuth = config('MAIL_USERNAME') !== null;
    $correo->Username = (string) config('MAIL_USERNAME', '');
    $correo->Password = (string) config('MAIL_PASSWORD', '');
    $correo->Timeout = 15;
    $correo->CharSet = PHPMailer::CHARSET_UTF8;

    $cifrado = strtolower(config('MAIL_ENCRYPTION', 'tls'));

    if ($cifrado === 'ssl') {
        $correo->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    } elseif ($cifrado === 'tls') {
        $correo->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    } else {
        $correo->SMTPAutoTLS = false;
    }

    $correo->setFrom(
        (string) config('MAIL_FROM_ADDRESS', $correo->Username),
        (string) config('MAIL_FROM_NAME', 'JJ STORE')
    );
    $correo->addAddress($para, $nombre);
    $correo->isHTML(true);
    $correo->Subject = $asunto;
    $correo->Body = $html;
    $correo->AltBody = html_a_texto($html);
    $correo->send();
}

function html_a_texto(string $html): string
{
    $texto = preg_replace(['#<(style|head)[^>]*>.*?</\1>#si', '#<br\s*/?>#i', '#</(p|tr|h1|h2|div)>#i'], ['', "\n", "\n"], $html);
    $texto = html_entity_decode(strip_tags((string) $texto), ENT_QUOTES, 'UTF-8');

    return trim((string) preg_replace("/\n\s*\n+/", "\n\n", (string) preg_replace('/[ \t]+/', ' ', $texto)));
}
