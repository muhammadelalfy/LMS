<?php

declare(strict_types=1);

namespace Modules\Tenancy\Database;

use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\TenantDatabaseManagers\SQLiteDatabaseManager as BaseSqliteDatabaseManager;

/**
 * In WAL mode SQLite keeps "-wal" and "-shm" files beside each database. They go with it: a
 * stale log left behind would be replayed into the next school opened under the same name.
 */
class SqliteDatabaseManager extends BaseSqliteDatabaseManager
{
    private const SIDECAR_SUFFIXES = ['-wal', '-shm', '-journal'];

    public function createDatabase(TenantWithDatabase $tenant): bool
    {
        $this->removeSidecars($tenant->database()->getName());

        return parent::createDatabase($tenant);
    }

    public function deleteDatabase(TenantWithDatabase $tenant): bool
    {
        $deleted = parent::deleteDatabase($tenant);
        $this->removeSidecars($tenant->database()->getName());

        return $deleted;
    }

    private function removeSidecars(string $database): void
    {
        foreach (self::SIDECAR_SUFFIXES as $suffix) {
            $path = database_path($database.$suffix);

            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
