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

/** Ejecuta una consulta preparada y devuelve el statement. */
function consulta(string $sql, array $parametros = []): PDOStatement
{
    $sentencia = db()->prepare($sql);
    $sentencia->execute($parametros);

    return $sentencia;
}
