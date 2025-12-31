<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class TenantDB
{
    public static function table(string $table, string $tenantColumn = 'tenant_id'): Builder
    {
        // Avoid "Column 'tenant_id' in where clause is ambiguous" when joining other tenant-scoped tables.
        // If caller passed a qualified column like "u.tenant_id", respect it. Otherwise, qualify it using
        // the base table name or alias ("users as u" -> "u.tenant_id", "fee_rules" -> "fee_rules.tenant_id").
        $qualifiedTenantColumn = $tenantColumn;
        if (! str_contains($tenantColumn, '.')) {
            $aliasOrTable = self::aliasOrTable($table);
            $qualifiedTenantColumn = "{$aliasOrTable}.{$tenantColumn}";
        }

        return TenantContext::scope(DB::table($table), $qualifiedTenantColumn);
    }

    private static function aliasOrTable(string $table): string
    {
        $t = trim($table);

        // Match "table as alias"
        if (preg_match('/\s+as\s+([a-zA-Z0-9_]+)\s*$/i', $t, $m)) {
            return $m[1];
        }

        // Match "table alias"
        $parts = preg_split('/\s+/', $t);
        if (is_array($parts) && count($parts) >= 2) {
            return (string) end($parts);
        }

        // Fallback: the table name itself
        return $t;
    }
}


