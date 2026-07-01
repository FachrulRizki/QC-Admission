<?php

namespace App\Http\Controllers\QcAdmission;

use App\Http\Controllers\Controller;
use App\Services\QcAdmission\DashboardService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __construct(
        private readonly DashboardService $service
    ) {}

    /**
     * GET /api/dashboard?date_from=2026-07-01&date_to=2026-07-01
     * Default: hari ini.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to'   => 'nullable|date_format:Y-m-d',
        ]);

        $data = $this->service->getSummary(
            $request->query('date_from'),
            $request->query('date_to')
        );

        return response()->json($data);
    }
}
