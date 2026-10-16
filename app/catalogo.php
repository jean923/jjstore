<?php
/**
 * Datos y consultas compartidas del catálogo.
 */

declare(strict_types=1);

/** Tallas permitidas, en el orden en que se muestran. */
const TALLAS = ['XS', 'S', 'M', 'L', 'XL', 'XXL', '28', '30', '32', '34', '36', '38', 'Única'];

/** Colores permitidos (el color de muestra se define en public/js/api.js). */
const COLORES = ['Negro', 'Blanco', 'Gris', 'Azul', 'Beige', 'Verde', 'Rojo', 'Café', 'Rosa', 'Naranja', 'Terracota'];

/**
 * Variantes de varios productos en UNA sola consulta (evita el problema N+1).
 *
 * @param int[] $productoIds
 * @return array<int, array> producto_id => lista de variantes
 */
function variantes_por_producto(array $productoIds): array
{
    if (!$productoIds) {
        return [];
    }

    $marcadores = implode(',', array_fill(0, count($productoIds), '?'));
    $filas = consulta(
        "SELECT id, producto_id, talla, color, stock
         FROM producto_variantes
         WHERE producto_id IN ($marcadores)",
        array_values($productoIds)
    )->fetchAll();

    $ordenTalla = array_flip(TALLAS);
    $ordenColor = array_flip(COLORES);

    usort($filas, fn (array $a, array $b) =>
        [$ordenColor[$a['color']] ?? 99, $ordenTalla[$a['talla']] ?? 99]
        <=> [$ordenColor[$b['color']] ?? 99, $ordenTalla[$b['talla']] ?? 99]
    );

    $resultado = [];

    foreach ($filas as $fila) {
        $resultado[(int) $fila['producto_id']][] = [
            'id'    => (int) $fila['id'],
            'talla' => $fila['talla'],
            'color' => $fila['color'],
            'stock' => (int) $fila['stock'],
        ];
    }

    return $resultado;
}

/** Escapa los comodines de LIKE para buscar el texto literal. */
function patron_like(string $texto): string
{
    return '%' . addcslashes($texto, '%_\\') . '%';
}
