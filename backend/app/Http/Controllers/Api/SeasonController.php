<?php

namespace App\Http\Controllers\Api;

use App\Models\Season;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SeasonController extends CultivationController
{
    protected function query(Request $request)
    {
        return Season::whereHas('plot.farm', fn ($query) => $query->where('user_id', $request->user()->id))->with(['plot.farm', 'crop']);
    }

    protected function rules(Request $request): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'plot_id' => ['required', 'integer', Rule::exists('plots', 'id')->whereIn('farm_id', $request->user()->farms()->select('id'))],
            'crop_id' => ['required', 'integer', Rule::exists('crops', 'id')->where('user_id', $request->user()->id)],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'status' => ['required', Rule::in(['planned', 'active', 'completed', 'cancelled'])],
        ];
    }

    protected function create(Request $request, array $data)
    {
        return Season::create($data);
    }
}
