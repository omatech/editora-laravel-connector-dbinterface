<?php

namespace Omatech\Editora\Connector;

use Illuminate\Contracts\Config\Repository;
use Illuminate\Support\Carbon;

/**
 * Editors type publishing dates in their own time and Editora stores them with no zone, then compares them with the database's
 * NOW(); Laravel Cloud's MySQL runs in UTC, so content published and ended an hour late (two in summer). Every MySQL session a
 * request opens therefore uses the editors' zone, set as the offset of that moment: MySQL servers without time zone tables (the
 * official image) refuse a named zone, and each request computes the offset anew, so the clock change is followed.
 */
final class DatabaseTimezone
{
    private const DRIVERS = ['mysql', 'mariadb'];

    /** Sets the offset on the MySQL connections of the configuration, for the Laravel and Doctrine connections opened from now on. */
    public static function apply(Repository $config): void
    {
        $zone = (string) $config->get('editora.databaseTimezone', '');
        if ($zone === '') {
            return;
        }
        $offset = Carbon::now($zone)->format('P');
        foreach ((array) $config->get('database.connections', []) as $name => $connection) {
            if (in_array($connection['driver'] ?? null, self::DRIVERS, true)) {
                $config->set('database.connections.'.$name.'.timezone', $offset);
            }
        }
    }

    /** The statement a Doctrine connection runs when it opens, for a Laravel connection configured with an offset. */
    public static function initCommand(array $laravel): ?string
    {
        $offset = $laravel['timezone'] ?? null;
        if (!in_array($laravel['driver'] ?? null, self::DRIVERS, true) || !is_string($offset)) {
            return null;
        }
        if (preg_match('/^[+-](?:0\d|1[0-4]):[0-5]\d$/D', $offset) !== 1) {
            throw new \InvalidArgumentException('The database time zone must be an offset such as +01:00');
        }

        return "SET time_zone = '".$offset."'";
    }
}
