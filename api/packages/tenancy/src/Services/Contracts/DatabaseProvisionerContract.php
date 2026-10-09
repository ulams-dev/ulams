<?php

namespace Ulams\Tenancy\Services\Contracts;

interface DatabaseProvisionerContract
{
    /**
     * Creates the login role and the database it owns. Idempotent: an existing role gets its
     * password reset, an existing database is kept.
     */
    public function ensure(string $database, string $user, string $password): void;

    /**
     * Drops the database (terminating its connections) and the role. Missing ones are ignored.
     */
    public function drop(string $database, string $user): void;
}
