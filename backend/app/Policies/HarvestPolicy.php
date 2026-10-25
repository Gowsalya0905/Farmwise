<?php

namespace App\Policies;

use App\Models\Harvest;
use App\Models\User;

class HarvestPolicy
{
    public function view(User $user, Harvest $record): bool
    {
        return $user->id === $record->season->plot->farm->user_id;
    }

    public function update(User $user, Harvest $record): bool
    {
        return $this->view($user, $record);
    }

    public function delete(User $user, Harvest $record): bool
    {
        return $this->view($user, $record);
    }
}
