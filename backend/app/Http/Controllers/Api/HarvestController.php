<?php

namespace App\Http\Controllers\Api;

use App\Models\Harvest;
use App\Support\Decimal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class HarvestController extends SeasonRecordController
{
    protected function model(): string
    {
        return Harvest::class;
    }

    protected function recordRules(): array
    {
        return ['quantity' => ['required', 'numeric', 'min:0.0001', 'max:999999.9999', 'decimal:0,4'],
            'unit' => ['required', Rule::in(['kg', 'tonne', 'litre', 'piece'])]];
    }

    public function update(Request $request, string $id)
    {
        $data = $request->validate($this->rules($request) + ['user_id' => ['prohibited'], 'id' => ['prohibited']]);

        return DB::transaction(function () use ($request, $id, $data) {
            $record = $this->query($request)->lockForUpdate()->findOrFail($id);
            Gate::authorize('update', $record);
            if ($record->sales()->exists()) {
                $sold = $record->sales()->get()->sum(fn ($sale) => Decimal::integer((string) $sale->quantity, 4));
                if ((int) $data['season_id'] !== $record->season_id || $data['unit'] !== $record->unit ||
                    Decimal::integer((string) $data['quantity'], 4) < $sold ||
                    $data['date'] > $record->sales()->min('date')) {
                    throw ValidationException::withMessages(['quantity' => 'A sold harvest must keep its season and unit, cover all sold quantity, and precede every sale.']);
                }
            }
            $record->update($data);

            return response()->json($record->fresh());
        });
    }

    public function destroy(Request $request, string $id)
    {
        return DB::transaction(function () use ($request, $id) {
            $record = $this->query($request)->lockForUpdate()->findOrFail($id);
            Gate::authorize('delete', $record);
            abort_if($record->sales()->exists(), 409, 'Remove the sales before deleting this harvest.');
            $record->delete();

            return response()->noContent();
        });
    }
}
