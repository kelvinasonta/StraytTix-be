<?php

namespace Modules\Auth\app\Actions;

use Modules\Auth\app\DTOs\RegisterDTO;
use Modules\Auth\app\Models\User;

class RegisterUserAction
{
    public function handle(RegisterDTO $registerDto): User
    {
        $user = User::create([
            'first_name' => $registerDto->firstname,
            'last_name' => $registerDto->lastname,
            'email' => $registerDto->email,
            'phone' => $registerDto->phone,
            'timezone' => $registerDto->timezone,
            'password' => $registerDto->password,
            'account_type' => $registerDto->accountType,
        ]);

        return $user;
    }
}
