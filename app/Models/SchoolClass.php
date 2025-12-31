<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class SchoolClass extends Model
{
    use BelongsToTenant;

    protected $table = 'classes';

    protected $fillable = [
        'tenant_id',
        'name',
        'form_teacher_id',
        'description',
    ];
}


