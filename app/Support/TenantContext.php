<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Symfony\Component\HttpKernel\Exception\HttpException;

class TenantContext
{
    public static function id(): string
    {
        if (! tenancy()->initialized) {
            throw new HttpException(400, 'Tenant not resolved.');
        }

        return (string) tenant()->getTenantKey();
    }

    /**
     * Scope a query builder to the current tenant.
     */
    public static function scope(Builder $query, string $column = 'tenant_id'): Builder
    {
        return $query->where($column, self::id());
    }

    /**
     * Add tenant_id into a row/attributes array.
     *
     * @param array<string,mixed> $row
     * @return array<string,mixed>
     */
    public static function withTenant(array $row): array
    {
        if (! array_key_exists('tenant_id', $row)) {
            $row['tenant_id'] = self::id();
        }

        return $row;
    }
}



