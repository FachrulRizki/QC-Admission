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
        $user = auth('sanctum')->user();

        static::create([
            'user_id'    => $user?->id,
            'user_name'  => $user?->name,
            'user_role'  => $user?->role,
            'module'     => $module,
            'action'     => $action,
            'subject'    => $subject,
            'payload'    => $payload,
            'ip_address' => $ipAddress ?? request()->ip(),
        ]);
    }
}
