<?php

namespace App\Http\Controllers\Api;

use App\Models\Activity;
use App\Models\Plot;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

abstract class SeasonRecordController extends CultivationController
{
    abstract protected function model(): string;

    abstract protected function recordRules(): array;

    protected function query(Request $request)
    {
        return ($this->model())::whereHas('season.plot.farm', fn ($query) => $query->where('user_id', $request->user()->id))->with('season.plot.farm');
    }

    protected function rules(Request $request): array
    {
        return [
            'season_id' => ['required', 'integer', Rule::exists('seasons', 'id')->whereIn('plot_id',
                Plot::whereHas('farm', fn ($query) => $query->where('user_id', $request->user()->id))->select('id'))],
            'date' => $this->model() === Activity::class
                ? ['required', 'date_format:Y-m-d']
                : ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Kolkata')->toDateString()],
            'notes' => ['nullable', 'string', 'max:5000'],
        ] + $this->recordRules();
    }

    protected function create(Request $request, array $data)
    {
        return ($this->model())::create($data);
    }
}
