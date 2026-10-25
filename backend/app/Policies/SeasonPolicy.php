<?php

namespace App\Policies;

use App\Models\Season;
use App\Models\User;

class SeasonPolicy
{
    public function view(User $user, Season $record): bool
    {
        return $user->id === $record->plot->farm->user_id;
    }

    public function update(User $user, Season $record): bool
    {
        return $this->view($user, $record);
    }

    public function delete(User $user, Season $record): bool
    {
        return $this->view($user, $record);
    }
}
