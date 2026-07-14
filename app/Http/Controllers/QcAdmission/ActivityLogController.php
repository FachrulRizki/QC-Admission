<?php

namespace App\Http\Controllers\QcAdmission;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = ActivityLog::query()->latest();

        if ($s = $request->query('search')) {
            $query->where(function ($q) use ($s) {
                $q->where('user_name', 'like', "%{$s}%")
                  ->orWhere('subject',   'like', "%{$s}%")
                  ->orWhere('module',    'like', "%{$s}%")
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.petugas')) LIKE ?", ["%{$s}%"]);
            });
        }

        if ($v = $request->query('module'))    $query->where('module', $v);
        if ($v = $request->query('action'))    $query->where('action', $v);
        if ($v = $request->query('user'))      $query->where('user_name', 'like', "%{$v}%");
        if ($v = $request->query('date_from')) $query->whereDate('created_at', '>=', $v);
        if ($v = $request->query('date_to'))   $query->whereDate('created_at', '<=', $v);

        return response()->json(
            $query->paginate((int) $request->query('per_page', 30))
        );
    }
}
