<?php

namespace App\Support\Inactivity;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The account-status checks the API login path was missing.
 *
 * The web path (AuthenticatedSessionController::store) refuses a login when is_enable_login,
 * is_active, is_disable or delete_status say so. The API path did none of that: it went
 * straight from Auth::attempt() to createToken(), so a disabled account kept full mobile
 * access — and because config/sanctum.php sets 'expiration' => null, a token already issued
 * stayed valid forever.
 *
 * It also never stamped last_login_at, which made every mobile-only user look absent for
 * months to any inactivity check. Both gaps are closed here, in one place, so the three call
 * sites cannot drift apart.
 */
class ApiLoginGuard
{
    /** @return string|null a human-readable refusal, or null when the account may proceed */
    public static function denyReason(?User $user): ?string
    {
        if (!$user) {
            return 'Account not found.';
        }

        if ((int) $user->delete_status === 0) {
            return 'Your account has been deleted. Please contact your administrator.';
        }

        if ((int) $user->is_active === 0) {
            return 'Your account is deactivated. Please contact your administrator.';
        }

        if ($user->inactivity_banned_at !== null) {
            return 'Your account was suspended automatically due to inactivity. Please contact your administrator.';
        }

        if ((int) $user->is_enable_login === 0) {
            return 'Your account is disabled. Please contact your administrator.';
        }

        // A company owner disabled from above cascades to everyone under it, exactly as the
        // web path treats it.
        $parent = $user->created_by ? User::find($user->created_by) : null;
        if ($parent && ((int) $parent->is_enable_login === 0 || (int) $parent->delete_status === 0)) {
            return 'Your company account is disabled. Please contact your administrator.';
        }

        return null;
    }

    /**
     * Record the login so mobile-only users stop looking inactive.
     *
     * Written with a direct query rather than $user->update() because last_login_at sits in
     * User::$fillable and mass assignment there is a habit worth not spreading.
     */
    public static function stampLogin(User $user): void
    {
        DB::table('users')->where('id', $user->id)->update([
            'last_login_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** Revoke every API token for a user — the API equivalent of ending the session. */
    public static function revokeTokens(int $userId): void
    {
        DB::table('personal_access_tokens')
            ->where('tokenable_id', $userId)
            ->where('tokenable_type', 'LIKE', '%\\User')
            ->delete();
    }
}
