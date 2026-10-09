<?php

namespace Ulams\Tenancy\Services;

use PDO;
use PDOException;
use RuntimeException;
use Ulams\Tenancy\Services\Contracts\DatabaseProvisionerContract;

/**
 * For hosts that give the application no CREATEROLE/CREATEDB right (shared hosting, managed
 * PostgreSQL): the operator creates the database and its user, and this only checks that the
 * tenant can log in to it. `drop` leaves them alone; the operator removes them (ADR 0091).
 */
class ManualDatabaseProvisioner implements DatabaseProvisionerContract
{
    public function ensure(string $database, string $user, string $password): void
    {
        $config = (array) config('database.connections.' . config('database.default'), []);
        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s',
            $config['host'] ?? '127.0.0.1',
            $config['port'] ?? 5432,
            $database
        );

        try {
            $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_TIMEOUT => 10, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $pdo->query('SELECT 1');
        } catch (PDOException) {
            // never put the password in the message: it is stored in tenants.last_error
            throw new RuntimeException(
                "PostgreSQL database '{$database}' is not reachable as user '{$user}'. "
                . 'TENANCY_DATABASE_PROVISIONER=manual: create the database and the user yourself '
                . '(MyDevil: devil pgsql db add), with the password you gave to --db-password, then run the same command again.'
            );
        }
    }

    public function drop(string $database, string $user): void
    {
        // not ours to drop: the operator owns the database
    }
}
