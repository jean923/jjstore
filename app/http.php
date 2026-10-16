<?php
/**
 * Utilidades HTTP: respuestas JSON, lectura de datos y errores de la API.
 *
 * Formato de respuesta:
 *   éxito -> { "ok": true,  "datos": ... }
 *   error -> { "ok": false, "error": "mensaje", "errores": { "campo": "mensaje" } }
 */

declare(strict_types=1);

final class ErrorApi extends RuntimeException
{
    public function __construct(
        string $mensaje,
        public readonly int $codigoHttp = 400,
        public readonly array $errores = [],
        public readonly ?string $tipo = null
    ) {
        parent::__construct($mensaje);
    }
}

function responder(mixed $datos = null, int $codigo = 200): never
{
    enviar_json(['ok' => true, 'datos' => $datos], $codigo);
}

function responder_error(string $mensaje, int $codigo = 400, array $errores = [], ?string $tipo = null): never
{
    $cuerpo = ['ok' => false, 'error' => $mensaje];

    if ($tipo) {
        $cuerpo['tipo'] = $tipo;
    }

    if ($errores) {
        $cuerpo['errores'] = $errores;
    }

    enviar_json($cuerpo, $codigo);
}

function enviar_json(array $cuerpo, int $codigo): never
{
    http_response_code($codigo);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');

    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Acción solicitada: api/archivo.php?accion=nombre */
function accion(): string
{
    return (string) ($_GET['accion'] ?? '');
}

/** Lanza 405 si el método HTTP no es el esperado. */
function exigir_metodo(string $metodo): void
{
    if ($_SERVER['REQUEST_METHOD'] !== $metodo) {
        throw new ErrorApi('Método no permitido.', 405);
    }

    if ($metodo === 'POST') {
        verificar_csrf();
    }
}

/** Datos enviados en el cuerpo: JSON o formulario (multipart). */
function entrada(): array
{
    static $datos = null;

    if ($datos !== null) {
        return $datos;
    }

    $tipo = $_SERVER['CONTENT_TYPE'] ?? '';

    if (str_contains($tipo, 'application/json')) {
        $json = json_decode((string) file_get_contents('php://input'), true);

        if (!is_array($json)) {
            throw new ErrorApi('El cuerpo de la petición no es un JSON válido.');
        }

        return $datos = $json;
    }

    return $datos = $_POST;
}

/** URL base pública del sitio (para enlaces e imágenes en los correos). */
function url_base(): string
{
    $configurada = config('APP_URL');

    if ($configurada) {
        return rtrim($configurada, '/');
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // /ruta/public/api/archivo.php -> /ruta/public
    $carpeta = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/', 2)), '/');

    return ($https ? 'https' : 'http') . '://' . $host . $carpeta;
}

/** Convierte una imagen guardada (URL o ruta relativa) en URL absoluta. */
function url_imagen(string $imagen): string
{
    return preg_match('#^https?://#i', $imagen) ? $imagen : url_base() . '/' . ltrim($imagen, '/');
}
