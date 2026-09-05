<?php

namespace App\Http\Controllers\Crm\Phase4;

use App\Http\Controllers\Controller;
use App\Services\Analytics\AnalyticsDashboardService;
use Illuminate\Http\Request;

class AnalyticsController extends Controller
{
    public function index(Request $request, AnalyticsDashboardService $analytics)
    {
        $days = (int) $request->integer('days', 30);
        if (! in_array($days, [7, 30, 90], true)) {
            $days = 30;
        }

        $allowedTypes = ['blog', 'university', 'scholarship', 'job', 'guidebook'];
        $type = $request->string('type')->toString();
        $type = in_array($type, $allowedTypes, true) ? $type : null;

        return view('crm.phase4.analytics.index', [
            'days' => $days,
            'type' => $type,
            'summary' => $analytics->summary($days, $type),
            'trend' => $analytics->trend($days, $type),
            'topContent' => $analytics->topContent($days, $type),
            'hasData' => $analytics->hasData(),
            'guidebooks' => $analytics->guidebookCounts(),
            'types' => $allowedTypes,
        ]);
    }
}
