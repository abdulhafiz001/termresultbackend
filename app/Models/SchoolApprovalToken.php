<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SchoolApprovalToken extends Model
{
    /**
     * Always use the central database connection for approval tokens.
     * This ensures tokens are stored in the central DB even when
     * requests come from tenant subdomains.
     */
    protected $connection = 'mysql';

    protected $fillable = [
        'school_id',
        'token_hash',
        'expires_at',
        'used_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}


