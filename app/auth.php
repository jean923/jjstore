<?php
/**
 * Sesiones, usuario autenticado, roles y protección CSRF.
 */

declare(strict_types=1);

function iniciar_sesion(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    session_name('JJSTORE_SESION');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,           // JavaScript no puede leer la cookie
        'samesite' => 'Lax',          // no se envía en peticiones de otros sitios
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    ]);
    session_start();
}

function iniciar_sesion_usuario(int $usuarioId): void
{
    session_regenerate_id(true); // evita fijación de sesión
    $_SESSION['usuario_id'] = $usuarioId;
    $_SESSION['csrf'] = bin2hex(random_bytes(32));
}

function cerrar_sesion_usuario(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

/**
 * Usuario de la sesión actual (con su tienda si es vendedor) o null.
 * Se consulta una vez por petición; $recargar = true fuerza leerlo de nuevo.
 */
function usuario_actual(bool $recargar = false): ?array
{
    static $cache = [];

    $id = $_SESSION['usuario_id'] ?? null;

    if (!$id) {
        return null;
    }

    if (!$recargar && array_key_exists($id, $cache)) {
        return $cache[$id];
    }

    $usuario = consulta(
        'SELECT u.id, u.nombre, u.email, u.telefono, u.rol,
                t.id AS tienda_id, t.nombre AS tienda_nombre
         FROM usuarios u
         LEFT JOIN tiendas t ON t.usuario_id = u.id
         WHERE u.id = ?',
        [$id]
    )->fetch();

    return $cache[$id] = ($usuario ?: null);
}

function requerir_usuario(): array
{
    $usuario = usuario_actual();

    if (!$usuario) {
        throw new ErrorApi('Debes iniciar sesión para continuar.', 401);
    }

    return $usuario;
}

function requerir_vendedor(): array
{
    $usuario = requerir_usuario();

    if ($usuario['rol'] !== 'vendedor' || !$usuario['tienda_id']) {
        throw new ErrorApi('Esta sección es solo para vendedores.', 403);
    }

    $usuario['tienda_id'] = (int) $usuario['tienda_id'];

    return $usuario;
}

/** Datos públicos del usuario para enviar al navegador. */
function usuario_publico(?array $usuario): ?array
{
    if (!$usuario) {
        return null;
    }

    return [
        'id'       => (int) $usuario['id'],
        'nombre'   => $usuario['nombre'],
        'email'    => $usuario['email'],
        'telefono' => $usuario['telefono'],
        'rol'      => $usuario['rol'],
        'tienda'   => $usuario['tienda_id']
            ? ['id' => (int) $usuario['tienda_id'], 'nombre' => $usuario['tienda_nombre']]
            : null,
    ];
}

function token_csrf(): string
{
    return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));
}

/**
 * Toda petición POST debe incluir el encabezado X-CSRF-Token.
 * Otro sitio no puede leer el token ni enviar encabezados personalizados,
 * así que no puede hacer acciones a nombre del usuario.
 */
function verificar_csrf(): void
{
    $enviado = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';

    if (!is_string($enviado) || !hash_equals(token_csrf(), $enviado)) {
        // 403 estándar (Apache no reconoce códigos inventados como 419)
        throw new ErrorApi('La sesión expiró. Recarga la página e intenta de nuevo.', 403, [], 'csrf');
    }
}
