<?php
/**
 * PRUEBA AUTOMÁTICA DE LA API DE JJ STORE
 *
 * Recorre los flujos principales como lo haría un usuario y verifica cada respuesta:
 * comprador (sesión, CSRF, carrito, pago rechazado/aprobado, stock, correo, pedidos),
 * mi cuenta (editar nombre y celular, cambiar contraseña),
 * seguridad (pedidos ajenos, permisos de vendedor, archivos falsos) y vendedor
 * (resumen, ventas, estados, productos con imagen, tienda).
 *
 * IMPORTANTE: modifica la base de datos. Antes de correrla, reinicia los datos
 * importando database/jjstore.sql.
 *
 * Uso (con el sitio funcionando):
 *   php tests/prueba-api.php http://localhost/JJSTORE
 *   php tests/prueba-api.php http://localhost:8000
 *
 * Requiere la extensión curl de PHP (viene activa en WAMP y XAMPP).
 */
$base = rtrim($argv[1] ?? "http://localhost:8000", "/");
$fallos = 0;

final class Cliente {
    private string $cookies;
    public string $csrf = '';
    public function __construct(private string $base) { $this->cookies = tempnam(sys_get_temp_dir(), 'jj'); }
    public function pedir(string $metodo, string $ruta, $cuerpo = null, bool $multipart = false, bool $conCsrf = true): array {
        $ch = curl_init($this->base . '/api/' . $ruta);
        $headers = [];
        if ($metodo === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($multipart) { curl_setopt($ch, CURLOPT_POSTFIELDS, $cuerpo); }
            else { curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($cuerpo ?? new stdClass)); $headers[] = 'Content-Type: application/json'; }
            if ($conCsrf) $headers[] = 'X-CSRF-Token: ' . $this->csrf;
        }
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_COOKIEJAR => $this->cookies, CURLOPT_COOKIEFILE => $this->cookies]);
        $cuerpoResp = curl_exec($ch);
        $codigo = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $json = json_decode($cuerpoResp, true);
        if (isset($json['datos']['csrf'])) $this->csrf = $json['datos']['csrf'];
        return [$codigo, $json, $cuerpoResp];
    }
}

function verificar(string $nombre, bool $condicion, $extra = null) {
    global $fallos;
    echo ($condicion ? "  OK   " : "  FALLA") . " $nombre" . (!$condicion && $extra !== null ? ' -> ' . substr(is_string($extra) ? $extra : json_encode($extra, JSON_UNESCAPED_UNICODE), 0, 400) : '') . "\n";
    if (!$condicion) $fallos++;
}

echo "== Comprador ==\n";
$c = new Cliente($base);
[$s, $j] = $c->pedir('GET', 'auth.php?accion=sesion');
verificar('sesion anónima', $s === 200 && $j['datos']['usuario'] === null && strlen($c->csrf) === 64);

[$s, $j] = $c->pedir('POST', 'auth.php?accion=login', ['email' => 'comprador@jjstore.test', 'password' => 'Demo1234'], false, false);
verificar('login sin CSRF -> 403 tipo csrf', $s === 403 && ($j['tipo'] ?? '') === 'csrf', $j);

[$s, $j] = $c->pedir('POST', 'auth.php?accion=login', ['email' => 'comprador@jjstore.test', 'password' => 'mala']);
verificar('login contraseña mala -> 401', $s === 401, $j);

[$s, $j] = $c->pedir('POST', 'pedidos.php?accion=crear', ['items' => [['variante_id' => 1, 'cantidad' => 1]]]);
verificar('comprar sin sesión -> 401', $s === 401, $j);

[$s, $j] = $c->pedir('POST', 'auth.php?accion=login', ['email' => 'COMPRADOR@jjstore.test', 'password' => 'Demo1234']);
verificar('login comprador (email en mayúsculas)', $s === 200 && $j['datos']['usuario']['rol'] === 'comprador', $j);

[$s, $j] = $c->pedir('GET', 'vendedor.php?accion=resumen');
verificar('comprador no entra al panel -> 403', $s === 403, $j);

// variante 2 = producto 1 (M, Negro) stock 10 ; variante 70 = producto 10 gorra
[$s, $prod] = $c->pedir('GET', 'catalogo.php?accion=productos');
$gorra = array_values(array_filter($prod['datos'], fn ($p) => $p['id'] === 10))[0];
$varGorra = $gorra['variantes'][0];
$camiseta = array_values(array_filter($prod['datos'], fn ($p) => $p['id'] === 1))[0];
$varCam = array_values(array_filter($camiseta['variantes'], fn ($v) => $v['talla'] === 'M' && $v['color'] === 'Negro'))[0];
$items = [['variante_id' => $varCam['id'], 'cantidad' => 2], ['variante_id' => $varGorra['id'], 'cantidad' => 1]];

[$s, $j] = $c->pedir('POST', 'pedidos.php?accion=cotizar', ['items' => $items]);
verificar('cotizar: subtotal 175000 + envío 12000', $s === 200 && $j['datos']['subtotal'] == 175000 && $j['datos']['envio'] == 12000 && $j['datos']['total'] == 187000, $j);
verificar('cotizar no expone tienda_usuario_id', !isset($j['datos']['lineas'][0]['tienda_usuario_id']));

[$s, $j] = $c->pedir('POST', 'pedidos.php?accion=cotizar', ['items' => [['variante_id' => $varCam['id'], 'cantidad' => 99]]]);
verificar('cotizar sin stock suficiente -> problema', $s === 200 && count($j['datos']['problemas']) === 1, $j);

$envio = ['nombre' => 'Laura Gómez', 'email' => 'comprador@jjstore.test', 'telefono' => '3205551234', 'direccion' => 'Cra 43A # 1-50', 'ciudad' => 'Medellín', 'departamento' => 'Antioquia', 'notas' => 'Portería <b>24h</b>'];

[$s, $j] = $c->pedir('POST', 'pedidos.php?accion=crear', ['items' => $items, 'envio' => ['nombre' => ''], 'pago' => ['metodo' => 'tarjeta', 'numero' => '1234']]);
verificar('crear con datos inválidos -> 422 con errores', $s === 422 && isset($j['errores']['nombre']), $j);

[$s, $j] = $c->pedir('POST', 'pedidos.php?accion=crear', ['items' => $items, 'envio' => $envio, 'pago' => ['metodo' => 'tarjeta', 'numero' => '4000 0000 0000 0002', 'titular' => 'LAURA GOMEZ', 'vencimiento' => '12/30', 'cvv' => '123']]);
verificar('tarjeta rechazada -> 402', $s === 402, $j);

[$s, $j] = $c->pedir('POST', 'pedidos.php?accion=crear', ['items' => $items, 'envio' => $envio, 'pago' => ['metodo' => 'tarjeta', 'numero' => '4242 4242 4242 4242', 'titular' => 'LAURA GOMEZ', 'vencimiento' => '01/20', 'cvv' => '123']]);
verificar('tarjeta vencida -> 422', $s === 422 && isset($j['errores']['vencimiento']), $j);

[$s, $j] = $c->pedir('GET', 'catalogo.php?accion=productos');
$stockAntes = $varCam['stock'];

[$s, $j] = $c->pedir('POST', 'pedidos.php?accion=crear', ['items' => $items, 'envio' => $envio, 'pago' => ['metodo' => 'tarjeta', 'numero' => '4242 4242 4242 4242', 'titular' => 'LAURA GOMEZ', 'vencimiento' => '12/30', 'cvv' => '123']]);
verificar('compra aprobada -> 201', $s === 201 && preg_match('/^JJ-[A-Z0-9]{6}$/', $j['datos']['codigo'] ?? ''), $j);
$codigo = $j['datos']['codigo'] ?? '';
verificar('correo registrado (simulado o enviado)', in_array($j['datos']['correo'] ?? '', ['simulado', 'enviado'], true), $j);

[$s, $prod2] = $c->pedir('GET', 'catalogo.php?accion=productos');
$cam2 = array_values(array_filter($prod2['datos'], fn ($p) => $p['id'] === 1))[0];
$var2 = array_values(array_filter($cam2['variantes'], fn ($v) => $v['id'] === $varCam['id']))[0];
verificar("stock descontado ($stockAntes -> " . $var2['stock'] . ")", $var2['stock'] === $stockAntes - 2);

[$s, $j] = $c->pedir('GET', 'pedidos.php?accion=detalle&codigo=' . $codigo);
verificar('detalle del pedido', $s === 200 && count($j['datos']['items']) === 2 && $j['datos']['total'] == 187000 && $j['datos']['detalle_pago'] === 'VISA •••• 4242', $j);
verificar('detalle informa correo', in_array($j['datos']['correo']['estado'] ?? '', ['simulado', 'enviado'], true), $j['datos']['correo'] ?? null);

[$s, $j, $html] = $c->pedir('GET', 'pedidos.php?accion=correo&codigo=' . $codigo);
verificar('HTML del correo', $s === 200 && str_contains($html, $codigo) && str_contains($html, 'Gracias por tu compra'), substr($html, 0, 200));
verificar('correo escapa HTML del usuario', str_contains($html, '&lt;b&gt;24h&lt;/b&gt;') && !str_contains($html, '<b>24h</b>'));

[$s, $j] = $c->pedir('GET', 'pedidos.php?accion=mis-pedidos');
verificar('mis pedidos (5 = 4 demo + 1)', $s === 200 && count($j['datos']) === 5, count($j['datos'] ?? []));

echo "== Otro comprador no ve el pedido ==\n";
$otro = new Cliente($base);
$otro->pedir('GET', 'auth.php?accion=sesion');
$email = 'prueba' . time() . '@example.com';
[$s, $j] = $otro->pedir('POST', 'auth.php?accion=registro', ['nombre' => 'Persona Prueba', 'email' => $email, 'password' => 'clave123', 'rol' => 'comprador']);
verificar('registro comprador -> 201', $s === 201, $j);
[$s, $j] = $otro->pedir('POST', 'auth.php?accion=registro', ['nombre' => 'Persona Prueba', 'email' => $email, 'password' => 'clave123', 'rol' => 'comprador']);
verificar('registro con correo repetido -> 422', $s === 422 && isset($j['errores']['email']), $j);
[$s, $j] = $otro->pedir('GET', 'pedidos.php?accion=detalle&codigo=' . $codigo);
verificar('IDOR: pedido ajeno -> 404', $s === 404, $j);
[$s, $j] = $otro->pedir('POST', 'auth.php?accion=ser-vendedor', ['tienda_nombre' => 'Tienda Prueba ' . time(), 'tienda_ciudad' => 'Cali']);
verificar('comprador abre su tienda', $s === 201 && $j['datos']['usuario']['rol'] === 'vendedor', $j);

echo "== Mi cuenta ==\n";
$anonimo = new Cliente($base);
$anonimo->pedir('GET', 'auth.php?accion=sesion');
[$s, $j] = $anonimo->pedir('POST', 'auth.php?accion=actualizar-perfil', ['nombre' => 'Nadie']);
verificar('editar perfil sin sesión -> 401', $s === 401, $j);
[$s, $j] = $otro->pedir('POST', 'auth.php?accion=actualizar-perfil', ['nombre' => 'A', 'telefono' => 'abc']);
verificar('editar perfil con datos inválidos -> 422 (nombre y celular)', $s === 422 && isset($j['errores']['nombre'], $j['errores']['telefono']), $j);
[$s, $j] = $otro->pedir('POST', 'auth.php?accion=actualizar-perfil', ['nombre' => 'Persona Editada', 'telefono' => '300 123 4567']);
verificar('editar nombre y celular', $s === 200 && $j['datos']['usuario']['nombre'] === 'Persona Editada' && $j['datos']['usuario']['telefono'] === '300 123 4567', $j);
[$s, $j] = $otro->pedir('GET', 'auth.php?accion=sesion');
verificar('la sesión ya trae los datos nuevos', $j['datos']['usuario']['nombre'] === 'Persona Editada', $j);
[$s, $j] = $otro->pedir('POST', 'auth.php?accion=cambiar-password', ['actual' => 'equivocada', 'nueva' => 'NuevaClave9', 'confirmar' => 'NuevaClave9']);
verificar('cambiar contraseña con la actual incorrecta -> 422', $s === 422 && isset($j['errores']['actual']), $j);
[$s, $j] = $otro->pedir('POST', 'auth.php?accion=cambiar-password', ['actual' => 'clave123', 'nueva' => 'corta', 'confirmar' => 'otra']);
verificar('nueva contraseña corta y sin coincidir -> 422', $s === 422 && isset($j['errores']['nueva'], $j['errores']['confirmar']), $j);
[$s, $j] = $otro->pedir('POST', 'auth.php?accion=cambiar-password', ['actual' => 'clave123', 'nueva' => 'NuevaClave9', 'confirmar' => 'NuevaClave9']);
verificar('cambiar contraseña', $s === 200, $j);
$otro->pedir('POST', 'auth.php?accion=logout');
[$s, $j] = $otro->pedir('POST', 'auth.php?accion=login', ['email' => $email, 'password' => 'clave123']);
verificar('la contraseña anterior ya no sirve -> 401', $s === 401, $j);
[$s, $j] = $otro->pedir('POST', 'auth.php?accion=login', ['email' => $email, 'password' => 'NuevaClave9']);
verificar('entra con la contraseña nueva', $s === 200 && $j['datos']['usuario']['nombre'] === 'Persona Editada', $j);

echo "== Vendedor ==\n";
$v = new Cliente($base);
$v->pedir('GET', 'auth.php?accion=sesion');
[$s, $j] = $v->pedir('POST', 'auth.php?accion=login', ['email' => 'vendedor@jjstore.test', 'password' => 'Demo1234']);
verificar('login vendedor', $s === 200 && $j['datos']['usuario']['tienda']['nombre'] === 'JJ STORE Oficial', $j);

[$s, $j] = $v->pedir('GET', 'vendedor.php?accion=resumen');
verificar('resumen', $s === 200 && $j['datos']['pedidos'] >= 4 && count($j['datos']['ultimos_7_dias']) === 7, $j);

[$s, $j] = $v->pedir('GET', 'vendedor.php?accion=ventas');
$venta = array_values(array_filter($j['datos'], fn ($p) => $p['codigo'] === $codigo))[0] ?? null;
verificar('venta nueva visible solo con mis ítems', $venta && count($venta['items']) === 1 && $venta['total_tienda'] == 130000 && $venta['estado'] === 'nuevo', $venta);

[$s, $j] = $v->pedir('POST', 'vendedor.php?accion=estado-venta', ['pedido_id' => $venta['id'], 'estado' => 'entregado']);
verificar('transición inválida nuevo->entregado -> 422', $s === 422, $j);
[$s, $j] = $v->pedir('POST', 'vendedor.php?accion=estado-venta', ['pedido_id' => $venta['id'], 'estado' => 'enviado']);
verificar('marcar enviado', $s === 200 && $j['datos']['estado'] === 'enviado', $j);

// Cancelar otra venta devuelve stock: pedido demo 3 (nuevo) tiene Pantalón Cargo 32 Beige x1 y Camiseta Urban White S Blanco x1
[$s, $antes] = $v->pedir('GET', 'vendedor.php?accion=productos');
$stockDe = function ($lista, $pid, $talla, $color) { foreach ($lista as $p) if ($p['id'] === $pid) foreach ($p['variantes'] as $x) if ($x['talla'] === $talla && $x['color'] === $color) return $x['stock']; return null; };
$s1 = $stockDe($antes['datos'], 5, '32', 'Beige');
[$s, $j] = $v->pedir('POST', 'vendedor.php?accion=estado-venta', ['pedido_id' => 3, 'estado' => 'cancelado']);
verificar('cancelar venta', $s === 200, $j);
[$s, $despues] = $v->pedir('GET', 'vendedor.php?accion=productos');
verificar("cancelar devuelve stock ($s1 -> " . $stockDe($despues['datos'], 5, '32', 'Beige') . ")", $stockDe($despues['datos'], 5, '32', 'Beige') === $s1 + 1);

[$s, $j] = $v->pedir('POST', 'vendedor.php?accion=estado-venta', ['pedido_id' => 4, 'estado' => 'enviado']);
verificar('no puede tocar ventas de otra tienda -> 404', $s === 404, $j);

// Crear producto con imagen subida
// Imagen PNG válida de 1x1 píxel (no depende de la extensión GD)
$png = sys_get_temp_dir() . '/jj-prueba.png';
file_put_contents($png, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='));
$campos = ['nombre' => 'Producto Prueba', 'descripcion' => 'Desc', 'precio' => '75000', 'categoria_id' => '1', 'activo' => '1',
    'variantes' => json_encode([['talla' => 'M', 'color' => 'Negro', 'stock' => 5], ['talla' => 'L', 'color' => 'Negro', 'stock' => 0]]),
    'imagen' => new CURLFile($png, 'image/png', 'foto.png')];
[$s, $j] = $v->pedir('POST', 'vendedor.php?accion=guardar-producto', $campos, true);
verificar('crear producto con imagen', $s === 201, $j);
$nuevoId = $j['datos']['id'] ?? 0;

$falso = sys_get_temp_dir() . '/jj-falso.png'; file_put_contents($falso, '<?php echo "hack"; ?>');
$campos['imagen'] = new CURLFile($falso, 'image/png', 'shell.php.png');
[$s, $j] = $v->pedir('POST', 'vendedor.php?accion=guardar-producto', $campos, true);
verificar('rechaza archivo que no es imagen', $s === 422 && isset($j['errores']['imagen']), $j);

unset($campos['imagen']);
$campos['id'] = (string) $nuevoId; $campos['precio'] = '80000';
$campos['variantes'] = json_encode([['talla' => 'M', 'color' => 'Negro', 'stock' => 7], ['talla' => 'S', 'color' => 'Blanco', 'stock' => 2]]);
[$s, $j] = $v->pedir('POST', 'vendedor.php?accion=guardar-producto', $campos, true);
[$s2, $lista] = $v->pedir('GET', 'vendedor.php?accion=productos');
$p = array_values(array_filter($lista['datos'], fn ($x) => $x['id'] === $nuevoId))[0] ?? null;
verificar('editar producto (precio y variantes sincronizadas)', $s === 200 && $p && $p['precio'] == 80000 && count($p['variantes']) === 2 && $p['stock_total'] === 9 && str_starts_with($p['imagen'], 'uploads/productos/'), $p);

[$s, $j] = $v->pedir('POST', 'vendedor.php?accion=estado-producto', ['id' => $nuevoId, 'activo' => false]);
[$s2, $cat] = $v->pedir('GET', 'catalogo.php?accion=productos');
verificar('producto inactivo no sale en catálogo', !in_array($nuevoId, array_column($cat['datos'], 'id'), true));

[$s, $j] = $v->pedir('POST', 'vendedor.php?accion=eliminar-producto', ['id' => 9]);
verificar('no puede borrar producto de otra tienda -> 404', $s === 404, $j);
[$s, $j] = $v->pedir('POST', 'vendedor.php?accion=eliminar-producto', ['id' => $nuevoId]);
verificar('eliminar producto propio', $s === 200, $j);
$rutaImagen = dirname(__DIR__) . '/public/' . ($p['imagen'] ?? 'x');
verificar('imagen borrada del disco', !is_file($rutaImagen), $rutaImagen);

[$s, $j] = $v->pedir('POST', 'vendedor.php?accion=guardar-tienda', ['nombre' => 'Barrio Norte Studio', 'ciudad' => 'Medellín']);
verificar('nombre de tienda duplicado -> 422', $s === 422, $j);

[$s, $j] = $v->pedir('POST', 'auth.php?accion=logout', []);
[$s, $j] = $v->pedir('GET', 'vendedor.php?accion=resumen');
verificar('logout cierra la sesión', $s === 401, $j);

echo "\n" . ($fallos ? "$fallos FALLAS" : "TODO OK") . "\n";
exit($fallos ? 1 : 0);
