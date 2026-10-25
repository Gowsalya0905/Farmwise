<?php

namespace App\Policies;

use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    public function view(User $user, Sale $record): bool
    {
        return $user->id === $record->harvest->season->plot->farm->user_id;
    }

    public function update(User $user, Sale $record): bool
    {
        return $this->view($user, $record);
    }

    public function delete(User $user, Sale $record): bool
    {
        return $this->view($user, $record);
    }
}
