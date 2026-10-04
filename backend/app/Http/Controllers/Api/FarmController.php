<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FarmController extends Controller
{
    public function index(Request $request)
    {
        return response()->json($request->user()->farms()->orderBy('id')->paginate(25))->header('Cache-Control', 'no-store');
    }

    public function store(Request $request)
    {
        return response()->json($request->user()->farms()->create($this->validated($request)), 201);
    }

    public function show(Request $request, string $farm)
    {
        $record = $request->user()->farms()->findOrFail($farm);
        Gate::authorize('view', $record);

        return response()->json($record)->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, string $farm)
    {
        $record = $request->user()->farms()->findOrFail($farm);
        Gate::authorize('update', $record);
        $record->update($this->validated($request));

        return response()->json($record);
    }

    public function destroy(Request $request, string $farm)
    {
        $record = $request->user()->farms()->findOrFail($farm);
        Gate::authorize('delete', $record);
        $record->delete();

        return response()->noContent();
    }

    private function validated(Request $request): array
    {
        return $request->validate(['name' => ['required', 'string', 'max:255'], 'user_id' => ['prohibited']]);
    }
}
