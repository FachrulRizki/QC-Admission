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
     * Return all dashboard statistics and recent data in one call.
     */
    public function index(Request $request): JsonResponse
    {
        $data = $this->service->getSummary();

        return response()->json($data);
    }
}
