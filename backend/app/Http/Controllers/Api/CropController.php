<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;

class CropController extends CultivationController
{
    protected function query(Request $request)
    {
        return $request->user()->crops();
    }

    protected function rules(Request $request): array
    {
        return ['name' => ['required', 'string', 'max:255'], 'variety' => ['nullable', 'string', 'max:255']];
    }

    protected function create(Request $request, array $data)
    {
        return $request->user()->crops()->create($data);
    }
}
