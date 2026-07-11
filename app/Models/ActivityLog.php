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
        ?string $ipAddress = null
    ): void {
        // Auth disimpan di session (SSO Keycloak), bukan Sanctum token
        $authUser = session('auth_user');

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
}
