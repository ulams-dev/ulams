<?php

namespace Ulams\Tenancy\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use InvalidArgumentException;
use Ulams\Tenancy\Services\Contracts\DatabaseProvisionerContract;

class PostgresDatabaseProvisioner implements DatabaseProvisionerContract
{
    private const IDENTIFIER = '/^[a-z_][a-z0-9_]{0,62}$/';

    public function __construct(private DatabaseManager $db)
    {
    }

    public function ensure(string $database, string $user, string $password): void
    {
        $this->assertIdentifier($database);
        $this->assertIdentifier($user);
        $connection = $this->connection();
        $quotedPassword = $connection->getPdo()->quote($password);

        $roleExists = (bool) $connection->selectOne('SELECT 1 AS found FROM pg_roles WHERE rolname = ?', [$user]);
        $connection->statement(sprintf(
            '%s ROLE "%s" WITH LOGIN PASSWORD %s',
            $roleExists ? 'ALTER' : 'CREATE',
            $user,
            $quotedPassword
        ));

        $databaseExists = (bool) $connection->selectOne('SELECT 1 AS found FROM pg_database WHERE datname = ?', [$database]);
        if (!$databaseExists) {
            $connection->statement(sprintf('CREATE DATABASE "%s" OWNER "%s" ENCODING \'UTF8\'', $database, $user));
        }
        $connection->statement(sprintf('REVOKE ALL ON DATABASE "%s" FROM PUBLIC', $database));
    }

    public function drop(string $database, string $user): void
    {
        $this->assertIdentifier($database);
        $this->assertIdentifier($user);
        $connection = $this->connection();

        $connection->select(
            'SELECT pg_terminate_backend(pid) FROM pg_stat_activity WHERE datname = ? AND pid <> pg_backend_pid()',
            [$database]
        );
        $connection->statement(sprintf('DROP DATABASE IF EXISTS "%s"', $database));
        $connection->statement(sprintf('DROP ROLE IF EXISTS "%s"', $user));
    }

    private function connection(): ConnectionInterface
    {
        return $this->db->connection(config('ulams_tenancy.admin_connection', 'pgsql_admin'));
    }

    private function assertIdentifier(string $identifier): void
    {
        if (!preg_match(self::IDENTIFIER, $identifier)) {
            throw new InvalidArgumentException("Unsafe PostgreSQL identifier '{$identifier}'.");
        }
    }
}
