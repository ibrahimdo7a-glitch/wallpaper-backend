<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    // POST /v1/track/hit — fire-and-forget beacon from the site (pageview or heartbeat).
    public function track(Request $request, AnalyticsService $analytics): JsonResponse
    {
        $analytics->record($request);

        return response()->json(['ok' => true]);
    }
}
