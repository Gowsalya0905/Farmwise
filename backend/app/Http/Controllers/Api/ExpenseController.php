<?php

namespace App\Http\Controllers\Api;

use App\Models\Expense;
use Illuminate\Validation\Rule;

class ExpenseController extends SeasonRecordController
{
    protected function model(): string
    {
        return Expense::class;
    }

    protected function recordRules(): array
    {
        return ['category' => ['required', 'string', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:9999999999.99', 'decimal:0,2'],
            'currency' => ['required', Rule::in(['INR', 'USD', 'EUR', 'GBP'])]];
    }
}
