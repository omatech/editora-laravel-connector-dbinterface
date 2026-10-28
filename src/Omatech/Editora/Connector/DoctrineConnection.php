<?php

namespace Omatech\Editora\Connector;

/** Doctrine DBAL parameters for the Editora extractor, built from a Laravel database connection. */
final class DoctrineConnection
{
    private const DRIVERS = [
        'mysql' => 'pdo_mysql',
        'mariadb' => 'pdo_mysql',
        'pgsql' => 'pdo_pgsql',
        'sqlite' => 'pdo_sqlite',
        'sqlsrv' => 'pdo_sqlsrv',
    ];

    public static function fromLaravel(array $config): array
    {
        $config['driver'] = self::DRIVERS[$config['driver']] ?? $config['driver'];
        $config['user'] = $config['username'] ?? '';
        $config['dbname'] = $config['database'];
        $config['driverOptions'] = $config['options'] ?? [];
        if ($config['unix_socket'] ?? false) {
            unset($config['host']);
        }

        return $config;
    }
}
