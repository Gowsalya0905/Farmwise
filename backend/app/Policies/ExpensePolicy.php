<?php

namespace App\Policies;

use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    public function view(User $user, Expense $record): bool
    {
        return $user->id === $record->season->plot->farm->user_id;
    }

    public function update(User $user, Expense $record): bool
    {
        return $this->view($user, $record);
    }

    public function delete(User $user, Expense $record): bool
    {
        return $this->view($user, $record);
    }
}
