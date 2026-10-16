<?php
/**
 * API de cuentas de usuario.
 *
 *   GET  auth.php?accion=sesion        -> usuario actual + token CSRF
 *   POST auth.php?accion=registro      -> crear cuenta (comprador o vendedor)
 *   POST auth.php?accion=login         -> iniciar sesión
 *   POST auth.php?accion=logout        -> cerrar sesión
 *   POST auth.php?accion=ser-vendedor  -> un comprador abre su tienda
 */

declare(strict_types=1);

require dirname(__DIR__, 2) . '/app/bootstrap.php';

switch (accion()) {
    case 'sesion':
        exigir_metodo('GET');
        responder(['usuario' => usuario_publico(usuario_actual()), 'csrf' => token_csrf()]);

    case 'registro':
        exigir_metodo('POST');
        $datos = entrada();
        $v = new Validador($datos);

        $nombre = $v->texto('nombre', 'El nombre', 3, 120);
        $email = $v->email('email');
        $password = (string) ($datos['password'] ?? '');
        $rol = $v->opcion('rol', 'si quieres comprar o vender', ['comprador', 'vendedor']);

        if (strlen($password) < 8) {
            $v->agregarError('password', 'La contraseña debe tener al menos 8 caracteres.');
        } elseif (strlen($password) > 72) {
            $v->agregarError('password', 'La contraseña no puede superar 72 caracteres.');
        }

        if ($email && consulta('SELECT 1 FROM usuarios WHERE email = ?', [$email])->fetchColumn()) {
            $v->agregarError('email', 'Ya existe una cuenta con este correo. Inicia sesión.');
        }

        $tienda = null;

        if ($rol === 'vendedor') {
            $tienda = validar_datos_tienda($v);
        }

        $v->verificar();

        $pdo = db();
        $pdo->beginTransaction();

        try {
            consulta(
                'INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES (?, ?, ?, ?)',
                [$nombre, $email, password_hash($password, PASSWORD_DEFAULT), $rol]
            );
            $usuarioId = (int) $pdo->lastInsertId();

            if ($tienda) {
                crear_tienda($usuarioId, $tienda);
            }

            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        iniciar_sesion_usuario($usuarioId);
        responder(['usuario' => usuario_publico(usuario_actual(recargar: true)), 'csrf' => token_csrf()], 201);

    case 'login':
        exigir_metodo('POST');
        $datos = entrada();
        $email = mb_strtolower(trim((string) ($datos['email'] ?? '')));
        $password = (string) ($datos['password'] ?? '');

        if ($email === '' || $password === '') {
            throw new ErrorApi('Ingresa tu correo y tu contraseña.', 422);
        }

        $usuario = consulta('SELECT id, password_hash FROM usuarios WHERE email = ?', [$email])->fetch();

        // Si el correo no existe se verifica contra un hash ficticio para que la
        // respuesta tarde lo mismo (no se puede adivinar qué correos están registrados).
        $hash = $usuario['password_hash'] ?? '$2y$10$A0.Yw1Di0NWQ/gO9l5alEutCX5CU0Pw64VGyc6aj1pQJ7KD0epGgC';

        if (!password_verify($password, $hash) || !$usuario) {
            throw new ErrorApi('Correo o contraseña incorrectos.', 401);
        }

        if (password_needs_rehash($usuario['password_hash'], PASSWORD_DEFAULT)) {
            consulta('UPDATE usuarios SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $usuario['id']]);
        }

        iniciar_sesion_usuario((int) $usuario['id']);
        responder(['usuario' => usuario_publico(usuario_actual()), 'csrf' => token_csrf()]);

    case 'logout':
        exigir_metodo('POST');
        cerrar_sesion_usuario();
        responder(['usuario' => null, 'csrf' => token_csrf()]);

    case 'ser-vendedor':
        exigir_metodo('POST');
        $usuario = requerir_usuario();

        if ($usuario['tienda_id']) {
            throw new ErrorApi('Tu cuenta ya tiene una tienda.', 409);
        }

        $v = new Validador(entrada());
        $tienda = validar_datos_tienda($v);
        $v->verificar();

        $pdo = db();
        $pdo->beginTransaction();

        try {
            crear_tienda((int) $usuario['id'], $tienda);
            consulta("UPDATE usuarios SET rol = 'vendedor' WHERE id = ?", [$usuario['id']]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        iniciar_sesion_usuario((int) $usuario['id']);
        responder(['usuario' => usuario_publico(usuario_actual(recargar: true)), 'csrf' => token_csrf()], 201);

    default:
        throw new ErrorApi('Acción no encontrada.', 404);
}

function validar_datos_tienda(Validador $v): array
{
    $tienda = [
        'nombre'      => $v->texto('tienda_nombre', 'El nombre de la tienda', 3, 120),
        'ciudad'      => $v->texto('tienda_ciudad', 'La ciudad', 2, 80),
        'descripcion' => $v->texto('tienda_descripcion', 'La descripción', 0, 500, requerido: false),
    ];

    if ($tienda['nombre'] && consulta('SELECT 1 FROM tiendas WHERE nombre = ?', [$tienda['nombre']])->fetchColumn()) {
        $v->agregarError('tienda_nombre', 'Ya existe una tienda con ese nombre.');
    }

    return $tienda;
}

function crear_tienda(int $usuarioId, array $tienda): void
{
    consulta(
        'INSERT INTO tiendas (usuario_id, nombre, ciudad, descripcion) VALUES (?, ?, ?, ?)',
        [$usuarioId, $tienda['nombre'], $tienda['ciudad'], $tienda['descripcion']]
    );
}
