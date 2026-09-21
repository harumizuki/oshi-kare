<?php

namespace App\Policies;

use App\Models\Target;
use App\Models\User;

class TargetPolicy
{
    public function view(User $user, Target $target): bool
    {
        return $user->id === $target->user_id;
    }
}
