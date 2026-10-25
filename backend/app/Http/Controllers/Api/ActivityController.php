<?php

namespace App\Http\Controllers\Api;

use App\Models\Activity;

class ActivityController extends SeasonRecordController
{
    protected function model(): string
    {
        return Activity::class;
    }

    protected function recordRules(): array
    {
        return ['type' => ['required', 'string', 'max:100']];
    }
}
