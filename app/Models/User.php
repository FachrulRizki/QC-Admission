<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',   // dipakai untuk login lokal (testing)
        'role',       // snapshot role untuk display
        'sso_id',     // ID user di Keycloak
        'login_type', // 'sso' atau 'local'
    ];

    protected $table = 'qcw_users';

    protected $hidden = [
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }
}
