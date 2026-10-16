<?php
/**
 * Punto de arranque de la API.
 * Cada archivo de public/api/ incluye este archivo antes de hacer cualquier cosa.
 */

declare(strict_types=1);

define('RAIZ', dirname(__DIR__));

require RAIZ . '/app/config.php';
require RAIZ . '/app/BaseDatos.php';
require RAIZ . '/app/http.php';
require RAIZ . '/app/Validador.php';
require RAIZ . '/app/auth.php';

if (is_file(RAIZ . '/vendor/autoload.php')) {
    require RAIZ . '/vendor/autoload.php';
}

date_default_timezone_set(config('APP_TIMEZONE', 'America/Bogota'));

// Los errores nunca se muestran al usuario: se guardan en storage/logs.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', RAIZ . '/storage/logs/php-error.log');
error_reporting(E_ALL);

set_error_handler(function (int $nivel, string $mensaje, string $archivo, int $linea): bool {
    if (!(error_reporting() & $nivel)) {
        return false;
    }
    throw new ErrorException($mensaje, 0, $nivel, $archivo, $linea);
});

set_exception_handler(function (Throwable $e): void {
    if ($e instanceof ErrorApi) {
        responder_error($e->getMessage(), $e->codigoHttp, $e->errores, $e->tipo);
    }

    error_log(sprintf("[%s] %s en %s:%d\n%s", get_class($e), $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString()));

    $mensaje = config('APP_DEBUG') === 'true'
        ? $e->getMessage()
        : 'Ocurrió un error inesperado. Intenta de nuevo en unos minutos.';

    responder_error($mensaje, 500);
});

iniciar_sesion();
