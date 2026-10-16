<?php
/**
 * Conexión única a MySQL con PDO.
 * Todas las consultas usan sentencias preparadas (protección contra inyección SQL).
 */

declare(strict_types=1);

final class BaseDatos
{
    private static ?PDO $conexion = null;

    public static function conexion(): PDO
    {
        if (self::$conexion === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                config('DB_HOST', '127.0.0.1'),
                config('DB_PORT', '3306'),
                config('DB_NAME', 'jjstore')
            );

            self::$conexion = new PDO($dsn, config('DB_USER', 'root'), config('DB_PASS', ''), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }

        return self::$conexion;
    }
}

function db(): PDO
{
    return BaseDatos::conexion();
}

/**
 * Mensaje claro para los errores de base de datos que vienen de la instalación
 * (no de la aplicación). Devuelve null si el error es de otro tipo.
 */
function ayuda_error_bd(PDOException $e): ?string
{
    $mensaje = $e->getMessage();
    $codigoMysql = preg_match('/\[(\d{4})\]/', $mensaje, $m) ? (int) $m[1] : 0;
    $sqlState = (string) $e->getCode();

    if (stripos($mensaje, 'could not find driver') !== false) {
        return 'PHP no tiene activa la extensión pdo_mysql. En php.ini quita el ";" de la línea extension=pdo_mysql y reinicia Apache.';
    }

    if (in_array($codigoMysql, [2002, 2003, 2006], true)) {
        return 'No se pudo conectar a MySQL. Enciéndelo en el panel de XAMPP o WAMP (botón Start de MySQL).';
    }

    if ($codigoMysql === 1045) {
        return 'MySQL rechazó el usuario o la contraseña. Copia .env.example como .env y escribe tus datos en DB_USER y DB_PASS.';
    }

    if ($codigoMysql === 1049) {
        return "La base de datos '" . config('DB_NAME', 'jjstore') . "' no existe. Impórtala en phpMyAdmin con el archivo database/jjstore.sql.";
    }

    if ($sqlState === '42S02' || $sqlState === '42S22') {
        return 'A la base de datos le faltan tablas o columnas. Vuelve a importar database/jjstore.sql en phpMyAdmin.';
    }

    return null;
}

/** Ejecuta una consulta preparada y devuelve el statement. */
function consulta(string $sql, array $parametros = []): PDOStatement
{
    $sentencia = db()->prepare($sql);
    $sentencia->execute($parametros);

    return $sentencia;
}
