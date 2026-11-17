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
        $timezone = DatabaseTimezone::initCommand($config);
        $config['driver'] = self::DRIVERS[$config['driver']] ?? $config['driver'];
        $config['user'] = $config['username'] ?? '';
        $config['dbname'] = $config['database'];
        $config['driverOptions'] = $config['options'] ?? [];
        // Laravel sets the session zone after connecting; a Doctrine connection runs it as it opens, unless its options already do.
        if ($timezone !== null) {
            // Pdo\Mysql (PHP 8.4+) replaces the PDO::MYSQL_* constants that PHP 8.5 deprecates.
            $initCommand = \defined('Pdo\Mysql::ATTR_INIT_COMMAND') ? \Pdo\Mysql::ATTR_INIT_COMMAND : \PDO::MYSQL_ATTR_INIT_COMMAND;
            $config['driverOptions'] += [$initCommand => $timezone];
        }
        if ($config['unix_socket'] ?? false) {
            unset($config['host']);
        }

        return $config;
    }
}
