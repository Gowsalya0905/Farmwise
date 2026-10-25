<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Expense;
use App\Models\Harvest;
use App\Models\Plot;
use App\Models\Sale;
use App\Models\Season;
use App\Support\Decimal;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request)
    {
        $owner = fn ($query) => $query->where('user_id', $request->user()->id);
        $seasons = Season::whereHas('plot.farm', $owner);
        $today = now('Asia/Kolkata');
        $start = $today->copy()->startOfMonth()->toDateString();
        $end = $today->toDateString();
        $totals = [];
        foreach ([Expense::class => 'expenses', Sale::class => 'sales'] as $model => $kind) {
            $path = $kind === 'sales' ? 'harvest.season.plot.farm' : 'season.plot.farm';
            foreach ($model::whereHas($path, $owner)->whereBetween('date', [$start, $end])->lazyById(200) as $record) {
                $totals[$record->currency] ??= ['currency' => $record->currency, 'expenses' => 0, 'sales' => 0];
                $totals[$record->currency][$kind] += Decimal::integer((string) ($kind === 'sales' ? $record->total : $record->amount), 2);
            }
        }

        return response()->json([
            'counts' => [
                'farms' => $request->user()->farms()->count(),
                'plots' => Plot::whereHas('farm', $owner)->count(),
                'crops' => $request->user()->crops()->count(),
                'active_seasons' => (clone $seasons)->where('status', 'active')->count(),
                'planned_seasons' => (clone $seasons)->where('status', 'planned')->count(),
                'completed_seasons' => (clone $seasons)->where('status', 'completed')->count(),
                'month_activities' => Activity::whereHas('season.plot.farm', $owner)->whereBetween('date', [$start, $end])->count(),
                'month_harvests' => Harvest::whereHas('season.plot.farm', $owner)->whereBetween('date', [$start, $end])->count(),
            ],
            'month_label' => $today->format('F Y'),
            'financials' => array_values(array_map(function ($row) {
                return ['currency' => $row['currency'], 'expenses' => Decimal::format($row['expenses'], 2),
                    'sales' => Decimal::format($row['sales'], 2), 'net' => Decimal::format($row['sales'] - $row['expenses'], 2)];
            }, $totals)),
        ])->header('Cache-Control', 'no-store');
    }
}
