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
     * Roles yang tersedia:
     *  admin        — kelola aplikasi, akses semua menu
     *  qc_admission — entry QC, Edukasi Lanjutan, Up Selling, Batal Ranap
     *  kasir        — view only Batal Ranap (View Data Input)
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isQcAdmission(): bool
    {
        return $this->role === 'qc_admission';
    }

    public function isKasir(): bool
    {
        return $this->role === 'kasir';
    }

    public function canAccessMenu(string $menu): bool
    {
        return match ($this->role) {
            'admin'        => true,
            'qc_admission' => in_array($menu, ['dashboard', 'quality-control', 'edukasi-lanjutan', 'batal-ranap', 'up-selling', 'view-data-input']),
            'kasir'        => in_array($menu, ['batal-ranap-view']),
            default        => false,
        };
    }

    public function isSsoUser(): bool
    {
        return $this->login_type === 'sso';
    }
}
