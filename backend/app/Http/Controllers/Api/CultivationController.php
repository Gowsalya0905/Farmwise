<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plot;
use App\Models\Season;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

abstract class CultivationController extends Controller
{
    abstract protected function query(Request $request);

    abstract protected function rules(Request $request): array;

    abstract protected function create(Request $request, array $data);

    public function index(Request $request)
    {
        return response()->json($this->query($request)->orderBy('id')->paginate(25))->header('Cache-Control', 'no-store');
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules($request) + ['user_id' => ['prohibited'], 'id' => ['prohibited']]);

        return response()->json($this->create($request, $data), 201);
    }

    public function show(Request $request, string $id)
    {
        $record = $this->query($request)->findOrFail($id);
        Gate::authorize('view', $record);

        return response()->json($record)->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, string $id)
    {
        $record = $this->query($request)->findOrFail($id);
        Gate::authorize('update', $record);
        $data = $request->validate($this->rules($request) + ['user_id' => ['prohibited'], 'id' => ['prohibited']]);
        // Keep existing season history attached to its original farm.
        if ($record instanceof Season && $record->hasRecords()) {
            abort_if((int) $data['plot_id'] !== $record->plot_id || (int) $data['crop_id'] !== $record->crop_id, 409, 'A season with records cannot change its plot or crop.');
        }
        if ($record instanceof Plot && (int) $data['farm_id'] !== $record->farm_id) {
            abort_if($record->seasons()->exists(), 409, 'A plot with seasons cannot move to another farm.');
        }
        $record->update($data);

        return response()->json($record->fresh());
    }

    public function destroy(Request $request, string $id)
    {
        $record = $this->query($request)->findOrFail($id);
        Gate::authorize('delete', $record);
        if ($record instanceof Season) {
            abort_if($record->hasRecords(), 409, 'Remove the season records before deleting this season.');
        }
        if (method_exists($record, 'seasons')) {
            abort_if($record->seasons()->exists(), 409, 'This record has seasons. Remove those seasons first.');
        }
        $record->delete();

        return response()->noContent();
    }
}
