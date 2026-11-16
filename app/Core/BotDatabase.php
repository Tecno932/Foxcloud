<?php

declare(strict_types=1);

namespace FoxCraft\Core;

use PDO;
use RuntimeException;

final class BotDatabase
{
    private static ?PDO $connection = null;

    private function __construct()
    {
    }

    public static function connection(): PDO
    {
        if (self::$connection instanceof PDO) {
            return self::$connection;
        }

        $configFile = '/etc/foxcraft/botwp-ds.php';

        if (!is_readable($configFile)) {
            throw new RuntimeException(
                'La configuración de la base de datos del bot no está disponible.'
            );
        }

        $config = require $configFile;

        foreach (['host', 'port', 'database', 'username', 'password', 'charset'] as $key) {
            if (!isset($config[$key])) {
                throw new RuntimeException(
                    'La configuración de la base de datos del bot es inválida.'
                );
            }
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            (int) $config['port'],
            $config['database'],
            $config['charset']
        );

        self::$connection = new PDO(
            $dsn,
            $config['username'],
            $config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::ATTR_TIMEOUT => 5,
            ]
        );

        return self::$connection;
    }
}
