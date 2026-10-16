<?php
/**
 * PASARELA DE PAGO SIMULADA.
 *
 * No se conecta a ningún banco ni se cobra dinero. Imita el comportamiento
 * de una pasarela real para la demostración:
 *   - Tarjeta 4242 4242 4242 4242 (o cualquier número válido) -> aprobada
 *   - Tarjeta 4000 0000 0000 0002                             -> rechazada
 *   - PSE                                                     -> aprobado
 *   - Contra entrega                                          -> pendiente
 *
 * Por seguridad NUNCA se guarda el número completo ni el CVV:
 * solo la marca y los últimos 4 dígitos.
 */

declare(strict_types=1);

const BANCOS_PSE = [
    'Bancolombia', 'Davivienda', 'Banco de Bogotá', 'BBVA Colombia',
    'Banco de Occidente', 'Banco Popular', 'Scotiabank Colpatria', 'Nequi',
];

const TARJETA_RECHAZADA = '4000000000000002';

/** Valida el formato de los datos de pago. Lanza ErrorApi 422 si algo falla. */
function validar_datos_pago(mixed $pago): array
{
    $pago = is_array($pago) ? $pago : [];
    $v = new Validador($pago);
    $metodo = $v->opcion('metodo', 'un método de pago', ['tarjeta', 'pse', 'contraentrega']);
    $datos = ['metodo' => $metodo];

    if ($metodo === 'tarjeta') {
        $numero = preg_replace('/\D/', '', (string) ($pago['numero'] ?? ''));

        if (!preg_match('/^\d{13,19}$/', $numero) || !luhn_valido($numero)) {
            $v->agregarError('numero', 'El número de tarjeta no es válido.');
        }

        $v->texto('titular', 'El nombre del titular', 3, 80);

        $vencimiento = trim((string) ($pago['vencimiento'] ?? ''));

        if (!preg_match('#^(0[1-9]|1[0-2])\s*/\s*(\d{2})$#', $vencimiento, $partes)) {
            $v->agregarError('vencimiento', 'Usa el formato MM/AA.');
        } else {
            $finDeMes = DateTimeImmutable::createFromFormat('!Y-m', '20' . $partes[2] . '-' . $partes[1])->modify('last day of this month');

            if ($finDeMes < new DateTimeImmutable('today')) {
                $v->agregarError('vencimiento', 'La tarjeta está vencida.');
            }
        }

        if (!preg_match('/^\d{3,4}$/', (string) ($pago['cvv'] ?? ''))) {
            $v->agregarError('cvv', 'El CVV debe tener 3 o 4 dígitos.');
        }

        $datos['numero'] = $numero;
    }

    if ($metodo === 'pse') {
        $datos['banco'] = $v->opcion('banco', 'tu banco', BANCOS_PSE);
    }

    $v->verificar();

    return $datos;
}

/**
 * "Cobra" el pedido. Devuelve el resultado o lanza ErrorApi 402 si se rechaza.
 */
function procesar_pago_simulado(array $pago): array
{
    $referencia = 'SIM-' . strtoupper(bin2hex(random_bytes(5)));

    return match ($pago['metodo']) {
        'tarjeta' => $pago['numero'] === TARJETA_RECHAZADA
            ? throw new ErrorApi('Tu banco rechazó el pago (fondos insuficientes). Prueba con otra tarjeta.', 402)
            : [
                'estado_pago' => 'aprobado',
                'detalle'     => marca_tarjeta($pago['numero']) . ' •••• ' . substr($pago['numero'], -4),
                'referencia'  => $referencia,
            ],
        'pse' => [
            'estado_pago' => 'aprobado',
            'detalle'     => 'PSE · ' . $pago['banco'],
            'referencia'  => $referencia,
        ],
        'contraentrega' => [
            'estado_pago' => 'pendiente',
            'detalle'     => 'Pago contra entrega',
            'referencia'  => $referencia,
        ],
    };
}

/** Algoritmo de Luhn: el mismo que usan los bancos para validar números de tarjeta. */
function luhn_valido(string $numero): bool
{
    $suma = 0;
    $doble = false;

    for ($i = strlen($numero) - 1; $i >= 0; $i--) {
        $digito = (int) $numero[$i];

        if ($doble) {
            $digito *= 2;
            if ($digito > 9) {
                $digito -= 9;
            }
        }

        $suma += $digito;
        $doble = !$doble;
    }

    return $suma % 10 === 0;
}

function marca_tarjeta(string $numero): string
{
    return match (true) {
        str_starts_with($numero, '4')                  => 'VISA',
        (bool) preg_match('/^(5[1-5]|2[2-7])/', $numero) => 'MASTERCARD',
        (bool) preg_match('/^3[47]/', $numero)         => 'AMEX',
        default                                        => 'TARJETA',
    };
}
