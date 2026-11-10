<?php

namespace App\Policies;

use App\Models\PersonalFormacion;
use App\Models\User;

class PersonalFormacionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('personal.ver') && ($user->hasRole('administrador') || $user->hasRole('asesor') || $user->hasRole('pasante'));
    }

    public function view(User $user, PersonalFormacion $personal): bool
    {
        return $this->viewAny($user) && PersonalFormacion::query()->visibleTo($user)->whereKey($personal->getKey())->exists();
    }
}
