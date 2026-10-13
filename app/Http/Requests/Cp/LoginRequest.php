<?php

namespace App\Http\Requests\Cp;

use App\Http\Requests\Auth\LoginRequest as BaseLoginRequest;
use App\Models\User;

/**
 * Logging in to the control panel, which only staff can: a member's right
 * password is turned away as a wrong one is.
 */
class LoginRequest extends BaseLoginRequest
{
    protected function mayLogIn(User $user): bool
    {
        return $user->isStaff();
    }
}
