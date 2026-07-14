<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $fillable = [
        'user_id', 'user_name', 'user_role',
        'module', 'action', 'subject',
        'payload', 'ip_address',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    // Tambahkan 'petugas' ke output JSON secara otomatis
    protected $appends = ['petugas'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Helper — catat aktivitas dari mana saja.
     */
    public static function record(
        string  $module,
        string  $action,
        string  $subject = '',
        ?array  $payload = null,
        ?string $ipAddress = null,
        ?string $petugas = null
    ): void {
        // Auth disimpan di session (SSO Keycloak), bukan Sanctum token
        $authUser = session('auth_user');

        // Simpan petugas ke dalam payload agar bisa ditampilkan di activity log
        if ($petugas !== null) {
            $payload = array_merge($payload ?? [], ['petugas' => $petugas]);
        }

        static::create([
            'user_id'    => $authUser['id']       ?? null,
            'user_name'  => $authUser['name']     ?? null,
            'user_role'  => $authUser['roles'][0] ?? null,
            'module'     => $module,
            'action'     => $action,
            'subject'    => $subject,
            'payload'    => $payload,
            'ip_address' => $ipAddress ?? request()->ip(),
        ]);
    }

    /**
     * Accessor: ambil nama petugas dari payload jika ada.
     */
    public function getPetugasAttribute(): ?string
    {
        return $this->payload['petugas'] ?? null;
    }
}
