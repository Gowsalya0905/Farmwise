<?php

namespace App\Policies;

use App\Models\Crop;
use App\Models\User;

class CropPolicy
{
    public function view(User $user, Crop $record): bool
    {
        return $user->id === $record->user_id;
    }

    public function update(User $user, Crop $record): bool
    {
        return $this->view($user, $record);
    }

    public function delete(User $user, Crop $record): bool
    {
        return $this->view($user, $record);
    }
}
