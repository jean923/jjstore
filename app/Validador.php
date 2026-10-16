<?php
/**
 * Validación de datos de entrada.
 *
 *   $v = new Validador(entrada());
 *   $nombre = $v->texto('nombre', 'El nombre', min: 2, max: 120);
 *   $email  = $v->email('email');
 *   $v->verificar(); // lanza ErrorApi 422 con todos los errores juntos
 */

declare(strict_types=1);

final class Validador
{
    private array $errores = [];

    public function __construct(private readonly array $datos)
    {
    }

    public function texto(string $campo, string $etiqueta, int $min = 1, int $max = 255, bool $requerido = true): ?string
    {
        $valor = trim((string) ($this->datos[$campo] ?? ''));

        if ($valor === '') {
            if ($requerido) {
                $this->errores[$campo] = "$etiqueta es obligatorio.";
            }
            return null;
        }

        $largo = mb_strlen($valor);

        if ($largo < $min) {
            $this->errores[$campo] = "$etiqueta debe tener al menos $min caracteres.";
        } elseif ($largo > $max) {
            $this->errores[$campo] = "$etiqueta no puede superar $max caracteres.";
        }

        return $valor;
    }

    public function email(string $campo, string $etiqueta = 'El correo'): ?string
    {
        $valor = $this->texto($campo, $etiqueta, 5, 190);

        if ($valor !== null && !filter_var($valor, FILTER_VALIDATE_EMAIL)) {
            $this->errores[$campo] = 'Ingresa un correo electrónico válido.';
        }

        return $valor !== null ? mb_strtolower($valor) : null;
    }

    public function telefono(string $campo, string $etiqueta = 'El teléfono'): ?string
    {
        $valor = $this->texto($campo, $etiqueta, 7, 30);

        if ($valor !== null && !preg_match('/^\+?[0-9 ()-]{7,20}$/', $valor)) {
            $this->errores[$campo] = 'Ingresa un teléfono válido (solo números).';
        }

        return $valor;
    }

    public function entero(string $campo, string $etiqueta, int $min = 0, ?int $max = null): ?int
    {
        $valor = $this->datos[$campo] ?? null;

        if (filter_var($valor, FILTER_VALIDATE_INT) === false) {
            $this->errores[$campo] = "$etiqueta debe ser un número entero.";
            return null;
        }

        $valor = (int) $valor;

        if ($valor < $min || ($max !== null && $valor > $max)) {
            $this->errores[$campo] = $max === null
                ? "$etiqueta debe ser mayor o igual a $min."
                : "$etiqueta debe estar entre $min y $max.";
        }

        return $valor;
    }

    /** @param string[] $opciones */
    public function opcion(string $campo, string $etiqueta, array $opciones): ?string
    {
        $valor = (string) ($this->datos[$campo] ?? '');

        if (!in_array($valor, $opciones, true)) {
            $this->errores[$campo] = "Selecciona $etiqueta.";
            return null;
        }

        return $valor;
    }

    public function agregarError(string $campo, string $mensaje): void
    {
        $this->errores[$campo] = $mensaje;
    }

    public function verificar(): void
    {
        if ($this->errores) {
            throw new ErrorApi('Revisa los datos del formulario.', 422, $this->errores);
        }
    }
}
