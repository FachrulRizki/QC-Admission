<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'sso_id',
        'login_type',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    /**
     * Check if user is admin or supervisor.
     */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'supervisor']);
    }

    /**
     * Check if user logged in via SSO.
     */
    public function isSsoUser(): bool
    {
        return $this->login_type === 'sso';
    }
}
