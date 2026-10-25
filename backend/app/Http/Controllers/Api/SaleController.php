<?php

namespace App\Http\Controllers\Api;

use App\Models\Harvest;
use App\Models\Sale;
use App\Models\Season;
use App\Support\Decimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SaleController extends CultivationController
{
    protected function query(Request $request)
    {
        return Sale::whereHas('harvest.season.plot.farm', fn ($query) => $query->where('user_id', $request->user()->id))->with('harvest.season.plot.farm');
    }

    protected function rules(Request $request): array
    {
        return [
            'harvest_id' => ['required', 'integer', Rule::exists('harvests', 'id')->whereIn('season_id',
                Season::whereHas('plot.farm', fn ($query) => $query->where('user_id', $request->user()->id))->select('id'))],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:'.now('Asia/Kolkata')->toDateString()],
            'quantity' => ['required', 'numeric', 'min:0.0001', 'max:999999.9999', 'decimal:0,4'],
            'unit' => ['required', Rule::in(['kg', 'tonne', 'litre', 'piece'])],
            'unit_price' => ['required', 'numeric', 'min:0.01', 'max:999999.99', 'decimal:0,2'],
            'currency' => ['required', Rule::in(['INR', 'USD', 'EUR', 'GBP'])],
            'notes' => ['nullable', 'string', 'max:5000'],
            'total' => ['prohibited'],
        ];
    }

    protected function create(Request $request, array $data)
    {
        return DB::transaction(function () use ($request, $data) {
            $harvest = $this->harvest($request, $data['harvest_id']);
            $this->checkQuantity($harvest, $data);
            $data['total'] = Decimal::total((string) $data['quantity'], (string) $data['unit_price']);

            return Sale::create($data);
        });
    }

    private function harvest(Request $request, int|string $id): Harvest
    {
        return Harvest::whereHas('season.plot.farm', fn ($query) => $query->where('user_id', $request->user()->id))->lockForUpdate()->findOrFail($id);
    }

    private function checkQuantity(Harvest $harvest, array $data, ?int $except = null): void
    {
        $sales = $harvest->sales()->when($except, fn ($query) => $query->where('id', '!=', $except))->get();
        $sold = $sales->sum(fn ($sale) => Decimal::integer((string) $sale->quantity, 4));
        if ($data['unit'] !== $harvest->unit) {
            throw ValidationException::withMessages(['unit' => 'The sale unit must match the harvest unit.']);
        }
        if ($data['date'] < $harvest->date) {
            throw ValidationException::withMessages(['date' => 'The sale date cannot precede the harvest.']);
        }
        if ($sold + Decimal::integer((string) $data['quantity'], 4) > Decimal::integer((string) $harvest->quantity, 4)) {
            throw ValidationException::withMessages(['quantity' => 'The quantity exceeds the remaining harvest.']);
        }
    }

    public function update(Request $request, string $id)
    {
        $record = $this->query($request)->findOrFail($id);
        Gate::authorize('update', $record);
        $data = $request->validate($this->rules($request) + ['user_id' => ['prohibited'], 'id' => ['prohibited']]);
        if ((int) $data['harvest_id'] !== $record->harvest_id) {
            throw ValidationException::withMessages(['harvest_id' => 'A sale cannot move to another harvest.']);
        }

        return DB::transaction(function () use ($request, $id, $data) {
            $harvest = $this->harvest($request, $data['harvest_id']);
            $record = $this->query($request)->lockForUpdate()->findOrFail($id);
            $this->checkQuantity($harvest, $data, $record->id);
            $data['total'] = Decimal::total((string) $data['quantity'], (string) $data['unit_price']);
            $record->update($data);

            return response()->json($record->fresh());
        });
    }

    public function destroy(Request $request, string $id)
    {
        $record = $this->query($request)->findOrFail($id);
        Gate::authorize('delete', $record);

        return DB::transaction(function () use ($request, $record) {
            $this->harvest($request, $record->harvest_id);
            $record = $this->query($request)->lockForUpdate()->findOrFail($record->id);
            $record->delete();

            return response()->noContent();
        });
    }
}
