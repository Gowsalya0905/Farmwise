<?php

namespace App\Policies;

use App\Models\Plot;
use App\Models\User;

class PlotPolicy
{
    public function view(User $user, Plot $record): bool
    {
        return $user->id === $record->farm->user_id;
    }

    public function update(User $user, Plot $record): bool
    {
        return $this->view($user, $record);
    }

    public function delete(User $user, Plot $record): bool
    {
        return $this->view($user, $record);
    }
}
