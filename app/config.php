<?php
/**
 * Configuración: lee el archivo .env de la raíz del proyecto.
 * Si no existe, se usan los valores por defecto (pensados para WAMP/XAMPP).
 */

declare(strict_types=1);

function cargar_env(string $ruta): array
{
    if (!is_file($ruta)) {
        return [];
    }

    $valores = [];

    foreach (file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        $linea = trim($linea);

        if ($linea === '' || str_starts_with($linea, '#') || !str_contains($linea, '=')) {
            continue;
        }

        [$clave, $valor] = array_map('trim', explode('=', $linea, 2));

        // Quitar comillas envolventes: CLAVE="valor con espacios"
        if (strlen($valor) >= 2 && ($valor[0] === '"' || $valor[0] === "'") && $valor[-1] === $valor[0]) {
            $valor = substr($valor, 1, -1);
        }

        $valores[$clave] = $valor;
    }

    return $valores;
}

function config(string $clave, ?string $defecto = null): ?string
{
    static $env = null;
    $env ??= cargar_env(RAIZ . '/.env');

    $valor = $env[$clave] ?? null;

    return ($valor === null || $valor === '') ? $defecto : $valor;
}

/** Costo de envío fijo y monto a partir del cual el envío es gratis (COP). */
const COSTO_ENVIO = 12000;
const ENVIO_GRATIS_DESDE = 200000;
