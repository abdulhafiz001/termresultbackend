<?php

namespace App\Services;

use App\Models\School;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class TenantDatabaseProvisioner
{
    /**
     * Create a tenant database for the given school and run tenant migrations.
     */
    public function provision(School $school): School
    {
        // If DB already exists (or was partially provisioned), always attempt to run tenant migrations again.
        // This makes approval idempotent and lets us recover from partial failures.
        if ($school->database_name) {
            $this->migrateTenantDatabase($school->database_name);
            return $school->refresh();
        }

        $dbName = $this->generateDatabaseName($school->id);
        $this->createDatabase($dbName);

        // Persist db name early so IdentifyTenant can resolve it, but rollback if migration fails.
        $school->forceFill(['database_name' => $dbName])->save();

        try {
            $this->migrateTenantDatabase($dbName);
        } catch (\Throwable $e) {
            // Clean up the broken tenant DB and clear the database_name so the school can be approved again.
            try {
                $this->dropDatabase($dbName);
            } catch (\Throwable $e2) {
                // ignore cleanup failure
            }

            $school->forceFill(['database_name' => null])->save();
            throw $e;
        }

        return $school->refresh();
    }

    private function generateDatabaseName(int $schoolId): string
    {
        $prefix = env('TENANT_DB_PREFIX', 'school_');
        return $prefix.$schoolId.'_'.Str::lower(Str::random(8));
    }

    private function createDatabase(string $dbName): void
    {
        $adminConnection = env('TENANT_ADMIN_CONNECTION', Config::get('database.default'));
        $driver = (string) Config::get("database.connections.{$adminConnection}.driver", Config::get('database.default'));

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new \RuntimeException("Tenant DB creation is only supported on mysql/mariadb. Admin connection: {$adminConnection} (driver: {$driver})");
        }

        // Avoid SQL injection on DB name.
        if (! preg_match('/^[a-zA-Z0-9_]+$/', $dbName)) {
            throw new \InvalidArgumentException('Invalid tenant database name.');
        }

        DB::connection($adminConnection)->statement("CREATE DATABASE `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }

    private function dropDatabase(string $dbName): void
    {
        $adminConnection = env('TENANT_ADMIN_CONNECTION', Config::get('database.default'));
        $driver = (string) Config::get("database.connections.{$adminConnection}.driver", Config::get('database.default'));

        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            throw new \RuntimeException("Tenant DB drop is only supported on mysql/mariadb. Admin connection: {$adminConnection} (driver: {$driver})");
        }
        if (! preg_match('/^[a-zA-Z0-9_]+$/', $dbName)) {
            throw new \InvalidArgumentException('Invalid tenant database name.');
        }

        DB::connection($adminConnection)->statement("DROP DATABASE IF EXISTS `{$dbName}`");
    }

    private function migrateTenantDatabase(string $dbName): void
    {
        Config::set('database.connections.tenant.database', $dbName);
        DB::purge('tenant');

        // Run only tenant migrations.
        Artisan::call('migrate', [
            '--database' => 'tenant',
            '--path' => 'database/migrations/tenant',
            '--force' => true,
        ]);
    }
}


