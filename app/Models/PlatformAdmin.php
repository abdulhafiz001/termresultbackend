<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class PlatformAdmin extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $connection = 'mysql';
    protected $table = 'platform_admins';

    protected $fillable = [
        'full_name',
        'username',
        'email',
        'password',
        'role',
        'is_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];
}


