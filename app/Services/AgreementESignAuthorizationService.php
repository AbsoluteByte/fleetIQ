<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AgreementESignAuthorizationService
{
    public const RESET_SIGNED_ALLOWED_EMAIL = 'jawad@samoretraders.com';

    public function canResetSignedAgreement(?User $user = null): bool
    {
        $user ??= Auth::user();

        if (! $user) {
            return false;
        }

        return strtolower(trim((string) $user->email)) === self::RESET_SIGNED_ALLOWED_EMAIL;
    }
}
