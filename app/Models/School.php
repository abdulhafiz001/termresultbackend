<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class School extends Model
{
    /**
     * Always use the central database connection for the schools table.
     * This ensures school records are stored in the central DB even when
     * requests come from tenant subdomains.
     */
    protected $connection = 'mysql';

    protected $fillable = [
        'name',
        'subdomain',
        'contact_email',
        'contact_phone',
        'address',
        'status',
        'database_name',
        'decline_reason',
        'theme',
        'feature_toggles',
        'restrictions',
    ];

    protected function casts(): array
    {
        return [
            'theme' => 'array',
            'feature_toggles' => 'array',
            'restrictions' => 'array',
        ];
    }
}


