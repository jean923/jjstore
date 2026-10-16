<?php
/**
 * API pública del catálogo.
 *
 *   GET catalogo.php?accion=categorias
 *   GET catalogo.php?accion=productos&categoria=camisetas&q=texto
 *   GET catalogo.php?accion=opciones   -> tallas y colores permitidos
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';
require RAIZ . '/app/catalogo.php';

switch (accion()) {
    case 'categorias':
        exigir_metodo('GET');

        $categorias = consulta(
            'SELECT c.id, c.nombre, c.slug, COUNT(p.id) AS productos
             FROM categorias c
             LEFT JOIN productos p ON p.categoria_id = c.id AND p.activo = 1
             GROUP BY c.id, c.nombre, c.slug, c.orden
             ORDER BY c.orden, c.nombre'
        )->fetchAll();

        responder(array_map(fn (array $c) => [
            'id'        => (int) $c['id'],
            'nombre'    => $c['nombre'],
            'slug'      => $c['slug'],
            'productos' => (int) $c['productos'],
        ], $categorias));

    case 'productos':
        exigir_metodo('GET');

        $condiciones = ['p.activo = 1'];
        $parametros = [];

        $categoria = trim((string) ($_GET['categoria'] ?? ''));
        if ($categoria !== '' && $categoria !== 'todos') {
            $condiciones[] = 'c.slug = ?';
            $parametros[] = $categoria;
        }

        $texto = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 80);
        if ($texto !== '') {
            $condiciones[] = '(p.nombre LIKE ? OR c.nombre LIKE ? OR t.nombre LIKE ?)';
            array_push($parametros, patron_like($texto), patron_like($texto), patron_like($texto));
        }

        $productos = consulta(
            'SELECT p.id, p.nombre, p.descripcion, p.precio, p.imagen,
                    c.slug AS categoria, c.nombre AS categoria_nombre,
                    t.id AS tienda_id, t.nombre AS tienda, t.ciudad AS tienda_ciudad
             FROM productos p
             JOIN categorias c ON c.id = p.categoria_id
             JOIN tiendas t    ON t.id = p.tienda_id
             WHERE ' . implode(' AND ', $condiciones) . '
             ORDER BY p.creado_en DESC, p.id DESC
             LIMIT 200',
            $parametros
        )->fetchAll();

        $variantes = variantes_por_producto(array_map('intval', array_column($productos, 'id')));

        responder(array_map(function (array $p) use ($variantes) {
            $lista = $variantes[(int) $p['id']] ?? [];

            return [
                'id'               => (int) $p['id'],
                'nombre'           => $p['nombre'],
                'descripcion'      => $p['descripcion'],
                'precio'           => (float) $p['precio'],
                'imagen'           => $p['imagen'],
                'categoria'        => $p['categoria'],
                'categoria_nombre' => $p['categoria_nombre'],
                'tienda'           => ['id' => (int) $p['tienda_id'], 'nombre' => $p['tienda'], 'ciudad' => $p['tienda_ciudad']],
                'stock_total'      => array_sum(array_column($lista, 'stock')),
                'variantes'        => $lista,
            ];
        }, $productos));

    case 'opciones':
        exigir_metodo('GET');
        responder(['tallas' => TALLAS, 'colores' => COLORES]);

    default:
        throw new ErrorApi('Acción no encontrada.', 404);
}
