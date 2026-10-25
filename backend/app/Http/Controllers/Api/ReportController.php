<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Expense;
use App\Models\Harvest;
use App\Models\Plot;
use App\Models\Sale;
use App\Support\Decimal;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReportController extends Controller
{
    public function __invoke(Request $request)
    {
        $filters = $request->validate([
            'year' => ['nullable', 'integer', 'between:1900,2100'],
            'farm_id' => ['nullable', 'integer', Rule::exists('farms', 'id')->where('user_id', $request->user()->id)],
            'season_id' => ['nullable', 'integer', Rule::exists('seasons', 'id')->whereIn('plot_id',
                Plot::whereHas('farm', fn ($query) => $query->where('user_id', $request->user()->id))->select('id'))],
        ]);
        $money = [];
        $quantities = [];
        foreach ([Expense::class => 'expenses', Sale::class => 'sales', Harvest::class => 'harvests'] as $model => $kind) {
            $path = $kind === 'sales' ? 'harvest.season' : 'season';
            $query = $model::whereHas($path.'.plot.farm', fn ($query) => $query->where('user_id', $request->user()->id))
                ->with($path.'.plot.farm');
            if (! empty($filters['year'])) {
                $query->whereYear('date', $filters['year']);
            }
            if (! empty($filters['farm_id'])) {
                $query->whereHas($path.'.plot', fn ($query) => $query->where('farm_id', $filters['farm_id']));
            }
            if (! empty($filters['season_id'])) {
                $query->whereHas($path, fn ($query) => $query->where('id', $filters['season_id']));
            }
            foreach ($query->lazyById(200) as $record) {
                $season = $kind === 'sales' ? $record->harvest->season : $record->season;
                $year = substr($record->date, 0, 4);
                $base = ['season_id' => $season->id, 'season' => $season->name, 'farm_id' => $season->plot->farm_id,
                    'farm' => $season->plot->farm->name, 'year' => $year, 'plot_area' => $season->plot->area, 'area_unit' => $season->plot->area_unit];
                if ($kind === 'harvests') {
                    $key = $season->id.':'.$year.':'.$record->unit;
                    $quantities[$key] ??= $base + ['unit' => $record->unit, 'quantity' => 0];
                    $quantities[$key]['quantity'] += Decimal::integer((string) $record->quantity, 4);
                } else {
                    $key = $season->id.':'.$year.':'.$record->currency;
                    $money[$key] ??= $base + ['currency' => $record->currency, 'expenses' => 0, 'sales' => 0];
                    $money[$key][$kind] += Decimal::integer((string) ($kind === 'expenses' ? $record->amount : $record->total), 2);
                }
            }
        }
        $totals = [];
        foreach ($money as &$row) {
            $totals[$row['currency']] ??= ['currency' => $row['currency'], 'expenses' => 0, 'sales' => 0];
            $totals[$row['currency']]['expenses'] += $row['expenses'];
            $totals[$row['currency']]['sales'] += $row['sales'];
            $row = $this->formatMoney($row);
        }
        unset($row);

        return response()->json([
            'financials' => array_values($money),
            'totals' => array_values(array_map($this->formatMoney(...), $totals)),
            'harvests' => array_values(array_map(fn ($row) => array_replace($row, ['quantity' => Decimal::format($row['quantity'], 4)]), $quantities)),
        ])->header('Cache-Control', 'no-store');
    }

    private function formatMoney(array $row): array
    {
        $row['net'] = Decimal::format($row['sales'] - $row['expenses'], 2);
        $row['sales'] = Decimal::format($row['sales'], 2);
        $row['expenses'] = Decimal::format($row['expenses'], 2);

        return $row;
    }
}
