<?php

namespace App\Http\Controllers\Api;

use App\Models\Plot;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlotController extends CultivationController
{
    protected function query(Request $request)
    {
        return Plot::whereHas('farm', fn ($query) => $query->where('user_id', $request->user()->id))->with('farm');
    }

    protected function rules(Request $request): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'farm_id' => ['required', 'integer', Rule::exists('farms', 'id')->where('user_id', $request->user()->id)],
            'area' => ['nullable', 'numeric', 'min:0.0001', 'max:99999999.9999', 'decimal:0,4'],
            'area_unit' => ['required', Rule::in(['acre', 'hectare', 'sqm'])],
        ];
    }

    protected function create(Request $request, array $data)
    {
        return Plot::create($data);
    }
}
