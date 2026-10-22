<?php

namespace App\Services;

use App\Support\Inactivity\InactivityConfig;
use Illuminate\Support\Facades\DB;

/**
 * Single source of truth for "who has stopped using the ERP".
 *
 * The weekly command, the report page and the report email all call scan() — so the number
 * an admin reads on screen is by construction the same number the job would act on.
 *
 * IMPORTANT — this ERP has TWO login paths:
 *   web  : AuthenticatedSessionController::store()  → writes users.last_login_at
 *   API  : Api\AuthApiController::login()           → writes nothing at all
 * Judging activity by last_login_at alone therefore marks every mobile-app user as absent
 * for months. Three currently-working employees looked 19, 140 and 145 days idle by that
 * measure while using the app within the last week. Sanctum's last_used_at is folded in as
 * a first-class signal to close that gap.
 */
class InactivityAudit
{
    public function scan(): array
    {
        $threshold = InactivityConfig::thresholdDays();
        $grace     = InactivityConfig::graceDays();
        $roles     = array_map('mb_strtolower', InactivityConfig::exemptRoles());
        $pinned    = InactivityConfig::exemptUsers();

        $rows = $this->rows($threshold);

        $out = [
            'banned' => [], 'candidates' => [], 'warning' => [], 'exempt' => [], 'active' => [],
            'threshold'    => $threshold,
            'grace'        => $grace,
            'enforcing'    => InactivityConfig::enforcing(),
            'generated_at' => date('Y-m-d H:i:s'),
        ];

        foreach ($rows as $r) {
            $row = [
                'id'            => (int) $r->id,
                'name'          => $r->name,
                'email'         => $r->email,
                'type'          => $r->type,
                'last_login_at' => $r->last_login_at,
                'token_used_at' => $r->token_used_at,
                'last_activity' => $r->last_activity,
                'created_at'    => $r->created_at,
                'idle_days'     => (int) $r->idle_days,
                'never'         => $r->last_login_at === null && $r->token_used_at === null,
                'banned_at'     => $r->inactivity_banned_at,
            ];

            // Already carrying our stamp — its own bucket regardless of anything else,
            // otherwise a banned account would silently vanish from the report.
            if ($r->inactivity_banned_at !== null) {
                $out['banned'][] = $row;
                continue;
            }

            if ($reason = $this->exemptReason($r, $roles, $pinned, $grace)) {
                $row['reason'] = $reason;
                $out['exempt'][] = $row;
                continue;
            }

            if ($row['idle_days'] >= $threshold) {
                $out['candidates'][] = $row;
            } elseif ($row['idle_days'] >= $threshold - 2) {
                $out['warning'][] = $row;
            } else {
                $out['active'][] = $row;
            }
        }

        foreach (['banned', 'candidates', 'warning', 'exempt'] as $k) {
            usort($out[$k], static fn ($a, $b) => $b['idle_days'] <=> $a['idle_days']);
        }

        return $out;
    }

    /**
     * Why this account is spared, or null if it is fair game.
     *
     * @param string[] $roles  lower-cased exempt role names
     * @param int[]    $pinned
     */
    private function exemptReason(object $r, array $roles, array $pinned, int $grace): ?string
    {
        // An admin disabled this login by hand (no stamp of ours). Never our business.
        if ((int) $r->is_enable_login === 0) {
            return __('Disabled manually by an administrator');
        }

        if ($r->inactivity_exempt_until !== null && $r->inactivity_exempt_until >= date('Y-m-d')) {
            return __('Grace period until') . ' ' . $r->inactivity_exempt_until;
        }

        if (in_array((int) $r->id, $pinned, true)) {
            return __('Pinned as exempt by an administrator');
        }

        if ($r->type !== null && in_array(mb_strtolower(trim($r->type)), $roles, true)) {
            return __('Exempt role') . ': ' . $r->type;
        }

        if ((int) $r->account_age_days < $grace) {
            return __('New account') . ' (' . (int) $r->account_age_days . ' ' . __('days') . ')';
        }

        if ((int) $r->is_former === 1) {
            return __('Former employee');
        }

        if ((int) $r->on_leave === 1) {
            return __('On approved leave');
        }

        return null;
    }

    /**
     * One query, everything the classifier needs.
     *
     * GREATEST folds three signals into one number, and COALESCE(created_at) is the floor —
     * so "logged in long ago", "only ever used the mobile app" and "never signed in at all"
     * all resolve through the same arithmetic with no special-case branch.
     *
     * Scoped to created_by = COMPANY_ID on purpose: the other tenant's users are not ours to
     * touch, and disabling a `company` user cascades to every employee under it
     * (AuthenticatedSessionController checks the parent company's is_enable_login too).
     */
    private function rows(int $threshold): array
    {
        $tokenExpr = InactivityConfig::useTokenActivity() ? 'tok.token_used_at' : 'NULL';

        return DB::select(
            'SELECT
                 u.id, u.name, u.email, u.type, u.last_login_at, u.created_at,
                 u.inactivity_banned_at, u.inactivity_exempt_until, u.is_enable_login,
                 ' . $tokenExpr . ' AS token_used_at,
                 GREATEST(
                     COALESCE(u.last_login_at, u.created_at),
                     COALESCE(' . $tokenExpr . ', u.created_at),
                     u.created_at
                 ) AS last_activity,
                 DATEDIFF(NOW(), GREATEST(
                     COALESCE(u.last_login_at, u.created_at),
                     COALESCE(' . $tokenExpr . ', u.created_at),
                     u.created_at
                 )) AS idle_days,
                 DATEDIFF(NOW(), u.created_at) AS account_age_days,
                 EXISTS (
                     SELECT 1 FROM employees e
                     WHERE e.user_id = u.id AND e.is_active = 0
                 ) AS is_former,
                 EXISTS (
                     SELECT 1 FROM leaves l
                     JOIN employees e2 ON e2.id = l.employee_id
                     WHERE e2.user_id = u.id
                       AND l.status = "Approved"
                       AND l.end_date   >= DATE_SUB(CURDATE(), INTERVAL ? DAY)
                       AND l.start_date <= CURDATE()
                 ) AS on_leave
             FROM users u
             LEFT JOIN (
                 SELECT tokenable_id, MAX(last_used_at) AS token_used_at
                 FROM personal_access_tokens
                 WHERE tokenable_type LIKE "%\\\\User"
                 GROUP BY tokenable_id
             ) tok ON tok.tokenable_id = u.id
             WHERE u.delete_status = 1
               AND u.is_active = 1
               AND u.created_by = ?
               AND u.type NOT IN ("company", "super admin", "client")
             ORDER BY idle_days DESC',
            [$threshold, InactivityConfig::COMPANY_ID]
        );
    }

    /**
     * Apply the ban. Idempotent: the WHERE clause makes a second run a no-op, so a
     * double-firing cron cannot double-stamp or clobber a manual disable.
     *
     * Revoking API tokens is not optional — config/sanctum.php sets 'expiration' => null,
     * so a token issued once is valid forever and a banned user would keep full mobile
     * access. Deleting the tokens is the API equivalent of ending the session.
     */
    public function ban(array $row, string $actor = 'system'): bool
    {
        $affected = DB::table('users')
            ->where('id', $row['id'])
            ->where('is_enable_login', 1)
            ->whereNull('inactivity_banned_at')
            ->update([
                'is_enable_login'      => 0,
                'inactivity_banned_at' => date('Y-m-d H:i:s'),
                'updated_at'           => date('Y-m-d H:i:s'),
            ]);

        if (!$affected) {
            return false;
        }

        DB::table('personal_access_tokens')
            ->where('tokenable_id', $row['id'])
            ->where('tokenable_type', 'LIKE', '%\\User')
            ->delete();

        $this->log($row['id'], 'ban', __('No login for') . ' ' . $row['idle_days'] . ' ' . __('days'), $row, $actor);

        return true;
    }

    /**
     * Lift an automatic ban and open a grace window.
     *
     * The whereNotNull guard is the whole point of the marker column: an account an admin
     * disabled by hand has inactivity_banned_at = NULL and can never be re-enabled here.
     *
     * The grace window matters just as much — the user's last_login_at is still weeks old at
     * this moment, so without it the very next run would re-ban them before they ever get a
     * chance to sign in.
     */
    public function unban(int $userId, string $actor = 'system'): bool
    {
        $until = date('Y-m-d', strtotime('+' . InactivityConfig::unbanGraceDays() . ' days'));

        $affected = DB::table('users')
            ->where('id', $userId)
            ->whereNotNull('inactivity_banned_at')
            ->update([
                'is_enable_login'         => 1,
                'inactivity_banned_at'    => null,
                'inactivity_exempt_until' => $until,
                'updated_at'              => date('Y-m-d H:i:s'),
            ]);

        if ($affected) {
            $this->log($userId, 'unban', __('Grace period until') . ' ' . $until, [], $actor);
        }

        return (bool) $affected;
    }

    public function pinExempt(int $userId, string $actor = 'system'): void
    {
        $ids = InactivityConfig::exemptUsers();
        if (!in_array($userId, $ids, true)) {
            $ids[] = $userId;
            InactivityConfig::put('inactivity_exempt_users', implode(',', $ids));
            $this->log($userId, 'exempt', __('Pinned as exempt by an administrator'), [], $actor);
        }
    }

    public function unpinExempt(int $userId, string $actor = 'system'): void
    {
        $ids = array_values(array_diff(InactivityConfig::exemptUsers(), [$userId]));
        InactivityConfig::put('inactivity_exempt_users', implode(',', $ids));
        $this->log($userId, 'report', __('Removed from the exempt list'), [], $actor);
    }

    public function log(int $userId, string $action, ?string $reason = null, array $row = [], string $actor = 'system'): void
    {
        DB::table('inactivity_ban_logs')->insert([
            'user_id'       => $userId,
            'action'        => $action,
            'reason'        => $reason,
            'last_login_at' => $row['last_activity'] ?? ($row['last_login_at'] ?? null),
            'idle_days'     => $row['idle_days'] ?? null,
            'actor'         => $actor,
            'created_at'    => date('Y-m-d H:i:s'),
            'updated_at'    => date('Y-m-d H:i:s'),
        ]);
    }
}
