<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;

class ActivityPolicy
{
    public function view(User $user, Activity $record): bool
    {
        return $user->id === $record->season->plot->farm->user_id;
    }

    public function update(User $user, Activity $record): bool
    {
        return $this->view($user, $record);
    }

    public function delete(User $user, Activity $record): bool
    {
        return $this->view($user, $record);
    }
}
