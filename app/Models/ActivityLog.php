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

    protected $table = 'qcw_activity_logs';

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
     * Dibungkus try/catch agar kegagalan DB tidak crash request utama.
     */
    public static function record(
        string  $module,
        string  $action,
        string  $subject = '',
        ?array  $payload = null,
        ?string $ipAddress = null,
        ?string $petugas = null
    ): void {
        $authUser = session('auth_user');

        if ($petugas !== null) {
            $payload = array_merge($payload ?? [], ['petugas' => $petugas]);
        }

        try {
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
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('ActivityLog::record gagal menulis ke DB.', [
                'module'  => $module,
                'action'  => $action,
                'subject' => $subject,
                'error'   => $e->getMessage(),
            ]);
        }
    }

    /**
     * Accessor: ambil nama petugas dari payload jika ada.
     */
    public function getPetugasAttribute(): ?string
    {
        return $this->payload['petugas'] ?? null;
    }
}
