<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class V5DocumentAuthorizationService
{
    public const V5_DELETE_ALLOWED_EMAIL = 'jawad@samoretraders.com';

    public function canDeleteV5Documents(?User $user = null): bool
    {
        $user ??= Auth::user();

        if (! $user) {
            return false;
        }

        return strtolower(trim((string) $user->email)) === self::V5_DELETE_ALLOWED_EMAIL;
    }
}
