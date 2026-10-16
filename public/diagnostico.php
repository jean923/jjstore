<?php
/**
 * DIAGNÓSTICO DE INSTALACIÓN
 * Abre http://localhost/JJSTORE/diagnostico.php para ver si a tu computador le falta algo:
 * versión de PHP, extensiones, conexión a MySQL, tablas importadas y permisos de carpetas.
 *
 * Está escrito con sintaxis antigua a propósito: debe funcionar incluso si tu PHP es viejo,
 * que es justamente uno de los problemas que detecta. Solo responde en el mismo computador.
 */

$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

if (!in_array($ip, array('127.0.0.1', '::1'), true)) {
    http_response_code(404);
    exit;
}

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');

$raiz = dirname(__DIR__);
$revisiones = array();

function revisar(&$lista, $estado, $titulo, $detalle, $solucion = '')
{
    $lista[] = array('estado' => $estado, 'titulo' => $titulo, 'detalle' => $detalle, 'solucion' => $solucion);
}

function h($texto)
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

/* ---------- 1. PHP ---------- */
$versionOk = version_compare(PHP_VERSION, '8.0.0', '>=');
revisar($revisiones, $versionOk ? 'ok' : 'error', 'Versión de PHP', 'Tienes PHP ' . PHP_VERSION . '. Se necesita 8.0 o superior.',
    'Instala una versión reciente de XAMPP (con PHP 8.2) desde apachefriends.org.');

$extensiones = array(
    'pdo_mysql' => array(true, 'conectarse a MySQL'),
    'mbstring'  => array(true, 'manejar tildes y eñes'),
    'fileinfo'  => array(true, 'revisar las fotos que suben los vendedores'),
    'openssl'   => array(false, 'enviar correos reales por Gmail (solo si usas MAIL_DRIVER=smtp)'),
    'curl'      => array(false, 'correr la prueba automática tests/prueba-api.php'),
);

foreach ($extensiones as $nombre => $info) {
    $activa = extension_loaded($nombre);
    revisar($revisiones, $activa ? 'ok' : ($info[0] ? 'error' : 'aviso'), "Extensión $nombre",
        ($activa ? 'Activa. ' : 'No está activa. ') . 'Sirve para ' . $info[1] . '.',
        'En php.ini quita el ";" de la línea extension=' . $nombre . ' y reinicia Apache.');
}

/* ---------- 2. Configuración (.env) ---------- */
$env = array();
$rutaEnv = $raiz . '/.env';

if (is_file($rutaEnv)) {
    foreach (file($rutaEnv, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        $linea = trim($linea);
        if ($linea === '' || $linea[0] === '#' || strpos($linea, '=') === false) {
            continue;
        }
        $partes = explode('=', $linea, 2);
        $env[trim($partes[0])] = trim(trim($partes[1]), "\"'");
    }
    revisar($revisiones, 'ok', 'Archivo .env', 'Existe: se usan los datos que escribiste ahí.');
} else {
    revisar($revisiones, 'ok', 'Archivo .env', 'No existe (es normal). Se usan los valores de XAMPP/WAMP: usuario root sin contraseña.');
}

function valor_env($env, $clave, $defecto)
{
    return (isset($env[$clave]) && $env[$clave] !== '') ? $env[$clave] : $defecto;
}

$host = valor_env($env, 'DB_HOST', '127.0.0.1');
$puerto = valor_env($env, 'DB_PORT', '3306');
$base = valor_env($env, 'DB_NAME', 'jjstore');
$usuario = valor_env($env, 'DB_USER', 'root');
$clave = valor_env($env, 'DB_PASS', '');

/* ---------- 3. MySQL ---------- */
$pdo = null;

if (extension_loaded('pdo_mysql')) {
    try {
        $pdo = new PDO("mysql:host=$host;port=$puerto;charset=utf8mb4", $usuario, $clave, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));
        $version = $pdo->query('SELECT VERSION()')->fetchColumn();
        revisar($revisiones, 'ok', 'Conexión a MySQL', "Conectado a $host:$puerto como \"$usuario\" (servidor $version).");
    } catch (PDOException $e) {
        $texto = $e->getMessage();
        $solucion = 'Revisa que MySQL esté encendido en el panel de XAMPP o WAMP (botón Start).';
        if (strpos($texto, '[1045]') !== false) {
            $solucion = 'Usuario o contraseña incorrectos. Copia .env.example como .env y escribe tus datos en DB_USER y DB_PASS.';
        }
        revisar($revisiones, 'error', 'Conexión a MySQL', "No se pudo conectar a $host:$puerto: " . $texto, $solucion);
    }
}

if ($pdo) {
    $existe = $pdo->query('SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ' . $pdo->quote($base))->fetchColumn();

    if (!$existe) {
        revisar($revisiones, 'error', 'Base de datos', "No existe la base \"$base\".",
            'Abre phpMyAdmin → pestaña Importar → elige database/jjstore.sql → Importar.');
    } else {
        $pdo->exec('USE `' . str_replace('`', '', $base) . '`');
        $esperadas = array('usuarios', 'tiendas', 'categorias', 'productos', 'producto_variantes', 'pedidos', 'pedido_items', 'correos_enviados');
        $tablas = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        $faltan = array_diff($esperadas, $tablas);

        if ($faltan) {
            revisar($revisiones, 'error', 'Tablas', 'Faltan: ' . implode(', ', $faltan) . '.',
                'Vuelve a importar database/jjstore.sql en phpMyAdmin (borra y crea todo de nuevo).');
        } else {
            $usuarios = $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn();
            $productos = $pdo->query('SELECT COUNT(*) FROM productos')->fetchColumn();
            $pedidos = $pdo->query('SELECT COUNT(*) FROM pedidos')->fetchColumn();
            $demo = $pdo->query("SELECT COUNT(*) FROM usuarios WHERE email IN ('comprador@jjstore.test','vendedor@jjstore.test','barrionorte@jjstore.test')")->fetchColumn();

            revisar($revisiones, 'ok', 'Tablas', "Las 8 tablas existen: $usuarios usuarios, $productos productos, $pedidos pedidos.");
            revisar($revisiones, $demo == 3 ? 'ok' : 'aviso', 'Cuentas de prueba',
                $demo == 3 ? 'Están las 3 cuentas demo (contraseña Demo1234).' : 'No están las cuentas demo.',
                'Vuelve a importar database/jjstore.sql para tener las cuentas de prueba.');
        }
    }
}

/* ---------- 4. Carpetas con permiso de escritura ---------- */
$carpetas = array(
    'storage/logs' => 'guardar el registro de errores',
    'public/uploads/productos' => 'guardar las fotos que suben los vendedores',
);

foreach ($carpetas as $carpeta => $uso) {
    $ruta = $raiz . '/' . $carpeta;
    $ok = is_dir($ruta) && is_writable($ruta);
    revisar($revisiones, $ok ? 'ok' : 'error', "Carpeta $carpeta", ($ok ? 'Se puede escribir. ' : 'No existe o no se puede escribir. ') . "Sirve para $uso.",
        'Crea la carpeta si no existe y dale permiso de escritura.');
}

/* ---------- 5. Servidor ---------- */
$servidor = PHP_SAPI === 'cli-server' ? 'servidor integrado de PHP' : (isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : PHP_SAPI);

if (function_exists('apache_get_modules')) {
    $rewrite = in_array('mod_rewrite', apache_get_modules(), true);
    revisar($revisiones, $rewrite ? 'ok' : 'aviso', 'Apache mod_rewrite', $rewrite ? 'Activo.' : 'No está activo.',
        'Sin mod_rewrite entra por la carpeta public: http://localhost/JJSTORE/public/');
}

$errores = 0;
$avisos = 0;
foreach ($revisiones as $r) {
    if ($r['estado'] === 'error') { $errores++; }
    if ($r['estado'] === 'aviso') { $avisos++; }
}

$tienda = rtrim(str_replace('\\', '/', dirname(isset($_SERVER['SCRIPT_NAME']) ? $_SERVER['SCRIPT_NAME'] : '/')), '/') . '/';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Diagnóstico | JJ STORE</title>
    <style>
        :root { --fondo: #0d0d0d; --superficie: #171717; --borde: #2a2a2a; --texto: #f2f1ed; --gris: #a19e98; --ok: #6fbf73; --aviso: #e0b04f; --error: #e86b6b; }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--fondo); color: var(--texto); font: 15px/1.55 "Segoe UI", system-ui, sans-serif; padding: 40px max(16px, 5vw); }
        main { max-width: 860px; margin: 0 auto; }
        .logo { font-weight: 900; font-size: 28px; letter-spacing: -1px; }
        .logo small { display: block; font-size: 10px; letter-spacing: 4px; color: var(--gris); font-weight: 600; }
        h1 { font-size: 30px; margin: 28px 0 6px; }
        .resumen { padding: 18px 20px; border: 1px solid var(--borde); background: var(--superficie); margin: 22px 0; border-left: 4px solid var(--ok); }
        .resumen.con-errores { border-left-color: var(--error); }
        .resumen a { color: var(--texto); font-weight: 700; }
        ul { list-style: none; padding: 0; margin: 0; border: 1px solid var(--borde); background: var(--superficie); }
        li { display: grid; grid-template-columns: 30px 1fr; gap: 12px; padding: 16px 20px; border-top: 1px solid var(--borde); }
        li:first-child { border-top: 0; }
        .icono { width: 26px; height: 26px; border-radius: 50%; display: grid; place-items: center; font-weight: 800; font-size: 13px; color: #111; }
        .ok .icono { background: var(--ok); }
        .aviso .icono { background: var(--aviso); }
        .error .icono { background: var(--error); }
        strong { display: block; }
        .detalle { color: var(--gris); word-break: break-word; }
        .solucion { margin-top: 6px; color: var(--texto); }
        .solucion::before { content: "Cómo arreglarlo: "; font-weight: 700; }
        footer { color: var(--gris); font-size: 13px; margin-top: 22px; }
    </style>
</head>
<body>
<main>
    <div class="logo">JJSTORE.<small>DIAGNÓSTICO</small></div>
    <h1>¿Está todo listo para correr el proyecto?</h1>
    <p class="detalle">PHP <?php echo h(PHP_VERSION); ?> · <?php echo h($servidor); ?> · carpeta <?php echo h($raiz); ?></p>

    <div class="resumen<?php echo $errores ? ' con-errores' : ''; ?>">
        <?php if ($errores): ?>
            <strong>Hay <?php echo $errores; ?> problema<?php echo $errores > 1 ? 's' : ''; ?> por corregir.</strong>
            Arregla los puntos en rojo y recarga esta página.
        <?php else: ?>
            <strong>Todo listo.</strong>
            Abre la tienda: <a href="<?php echo h($tienda); ?>"><?php echo h($tienda); ?></a>
            <?php if ($avisos): ?> (los puntos en amarillo son opcionales)<?php endif; ?>
        <?php endif; ?>
    </div>

    <ul>
        <?php foreach ($revisiones as $r): ?>
            <li class="<?php echo h($r['estado']); ?>">
                <span class="icono" aria-hidden="true"><?php echo $r['estado'] === 'ok' ? '✓' : ($r['estado'] === 'aviso' ? '!' : '✕'); ?></span>
                <div>
                    <strong><?php echo h($r['titulo']); ?></strong>
                    <div class="detalle"><?php echo h($r['detalle']); ?></div>
                    <?php if ($r['estado'] !== 'ok' && $r['solucion'] !== ''): ?>
                        <div class="solucion"><?php echo h($r['solucion']); ?></div>
                    <?php endif; ?>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>

    <footer>Esta página solo se puede abrir desde el mismo computador. Más ayuda en docs/GUIA-EXPOSICION.md, sección 6.</footer>
</main>
</body>
</html>
