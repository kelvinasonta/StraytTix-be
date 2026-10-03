<?php

namespace Modules\Auth\app\Services;

use Jenssegers\Agent\Agent;
use Modules\Auth\app\Models\LoginAttempts;
use Modules\Auth\app\Models\User;
use Stevebauman\Location\Facades\Location;

class LogLoginAttemptService
{
    public function execute(?User $user, string $email, bool $status, string $ip, ?string $userAgent): void
    {
        $agent = new Agent;
        $agent->setUserAgent($userAgent);

        $location = Location::get($ip);

        if ($user) {
            $user->last_login_at = now();
            $user->last_login_ip = $ip;
            $user->save();
        }

        LoginAttempts::create([
            'user_id' => $user ? $user->id : null,
            'email' => $email,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'status' => $status,
            'created_at' => now(),
            'metadata' => [
                'device' => $agent->device(),
                'os' => $agent->platform(),
                'browser' => $agent->browser(),
                'city' => $location ? $location->cityName : null,
                'country' => $location ? $location->countryName : null,
            ],
        ]);
    }
}
