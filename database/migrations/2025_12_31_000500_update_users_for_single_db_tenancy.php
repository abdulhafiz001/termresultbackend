<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'tenant_id')) {
                $table->string('tenant_id')->nullable()->after('id');
            }

            if (! Schema::hasColumn('users', 'username')) {
                $table->string('username')->nullable()->after('name');
            }

            // NOTE: The original central users migration includes email as NOT NULL.
            // Tenant users (students/teachers/admins) are created without email in this app,
            // so we make email nullable below using driver-specific SQL.

            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable()->after('email');
            }

            if (! Schema::hasColumn('users', 'admission_number')) {
                $table->string('admission_number')->nullable()->after('phone');
            }

            if (! Schema::hasColumn('users', 'role')) {
                // Using string instead of enum for portability.
                $table->string('role', 30)->default('student')->index()->after('admission_number');
            }

            if (! Schema::hasColumn('users', 'status')) {
                $table->string('status', 30)->default('active')->index()->after('role');
            }

            if (! Schema::hasColumn('users', 'restrictions')) {
                $table->json('restrictions')->nullable()->after('status');
            }

            if (! Schema::hasColumn('users', 'restriction_reason')) {
                $table->text('restriction_reason')->nullable()->after('restrictions');
            }
        });

        // Make email nullable without requiring doctrine/dbal.
        $driver = Schema::getConnection()->getDriverName();
        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            // Keep length 255 and allow NULL.
            DB::statement('ALTER TABLE `users` MODIFY `email` VARCHAR(255) NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN email DROP NOT NULL');
        } // sqlite: ignored (dev/test)

        // Tenant FK
        if (! $this->hasForeignKey('users', 'users_tenant_id_foreign')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            });
        }

        // Drop global-unique constraints and re-add tenant-scoped uniques.
        Schema::table('users', function (Blueprint $table) {
            // Original central migration sets unique(email).
            if ($this->hasIndex('users', 'users_email_unique')) {
                $table->dropUnique('users_email_unique');
            }

            // In case these exist already from a previous attempt.
            if ($this->hasIndex('users', 'users_username_unique')) {
                $table->dropUnique('users_username_unique');
            }
            if ($this->hasIndex('users', 'users_admission_number_unique')) {
                $table->dropUnique('users_admission_number_unique');
            }

            // Composite uniques (tenant scoped).
            $table->unique(['tenant_id', 'email'], 'uniq_users_tenant_email');
            $table->unique(['tenant_id', 'username'], 'uniq_users_tenant_username');
            $table->unique(['tenant_id', 'admission_number'], 'uniq_users_tenant_admission');

            $table->index(['tenant_id', 'role'], 'idx_users_tenant_role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if ($this->hasIndex('users', 'idx_users_tenant_role')) {
                $table->dropIndex('idx_users_tenant_role');
            }
            if ($this->hasIndex('users', 'uniq_users_tenant_admission')) {
                $table->dropUnique('uniq_users_tenant_admission');
            }
            if ($this->hasIndex('users', 'uniq_users_tenant_username')) {
                $table->dropUnique('uniq_users_tenant_username');
            }
            if ($this->hasIndex('users', 'uniq_users_tenant_email')) {
                $table->dropUnique('uniq_users_tenant_email');
            }

            // Restore global unique(email) (best-effort).
            $table->unique('email');

            if ($this->hasForeignKey('users', 'users_tenant_id_foreign')) {
                $table->dropForeign('users_tenant_id_foreign');
            }

            if (Schema::hasColumn('users', 'tenant_id')) {
                $table->dropColumn('tenant_id');
            }
            if (Schema::hasColumn('users', 'username')) {
                $table->dropColumn('username');
            }
            if (Schema::hasColumn('users', 'phone')) {
                $table->dropColumn('phone');
            }
            if (Schema::hasColumn('users', 'admission_number')) {
                $table->dropColumn('admission_number');
            }
            if (Schema::hasColumn('users', 'role')) {
                $table->dropColumn('role');
            }
            if (Schema::hasColumn('users', 'status')) {
                $table->dropColumn('status');
            }
            if (Schema::hasColumn('users', 'restrictions')) {
                $table->dropColumn('restrictions');
            }
            if (Schema::hasColumn('users', 'restriction_reason')) {
                $table->dropColumn('restriction_reason');
            }
        });
    }

    private function hasIndex(string $table, string $indexName): bool
    {
        $driver = Schema::getConnection()->getDriverName();
        $dbName = Schema::getConnection()->getDatabaseName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $rows = DB::select(
                'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
                [$dbName, $table, $indexName]
            );
            return ! empty($rows);
        }

        if ($driver === 'pgsql') {
            $rows = DB::select('SELECT 1 FROM pg_indexes WHERE tablename = ? AND indexname = ? LIMIT 1', [$table, $indexName]);
            return ! empty($rows);
        }

        // sqlite/others: best-effort false
        return false;
    }

    private function hasForeignKey(string $table, string $fkName): bool
    {
        $driver = Schema::getConnection()->getDriverName();
        $dbName = Schema::getConnection()->getDatabaseName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $rows = DB::select(
                'SELECT 1
                 FROM information_schema.table_constraints
                 WHERE constraint_schema = ?
                   AND table_name = ?
                   AND constraint_name = ?
                   AND constraint_type = \'FOREIGN KEY\'
                 LIMIT 1',
                [$dbName, $table, $fkName]
            );
            return ! empty($rows);
        }

        // pg/sqlite: best-effort false
        return false;
    }
};



