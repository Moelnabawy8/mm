<?php

namespace App\Support\Telegram;

use Illuminate\Support\Facades\Log;

/**
 * The three guards that keep the observers from turning into a message cannon.
 *
 * Same three risks as WhatsApp (project/task bulk duplication fires `created` per
 * row, CLI loops over every task, no backstop on bugs), so the same three guards:
 *
 * 1. Suppression scope — ProjectController@duplicate and ApiController@duplicateProject copy
 *    tasks AND their comments with `new Model(); ->save()`, which fires `created` for every
 *    row. Duplicating a 50-task project with 10 comments each would enqueue 550 messages.
 * 2. CLI default-deny — runningInConsole() is true for seeders, tinker, migrate and
 *    FixTaskData (which loops over all tasks calling save()). Only our own commands
 *    opt in via allowCli().
 * 3. Per-request cap — a backstop so no bug, present or future, can flood the outbox.
 *
 * Independent static state from WhatsAppGate on purpose: the two channels are counted
 * and suppressed separately, so pausing one never affects the other.
 */
final class TelegramGate
{
    public const MAX_PER_REQUEST = 200;

    private static int $depth = 0;
    private static int $enqueued = 0;
    private static bool $cliAllowed = false;
    private static bool $capWarned = false;

    public static function suppressed(): bool
    {
        if (self::$depth > 0) {
            return true;
        }

        if (app()->runningInConsole() && !self::$cliAllowed) {
            return true;
        }

        if (self::$enqueued >= self::MAX_PER_REQUEST) {
            if (!self::$capWarned) {
                self::$capWarned = true;
                Log::warning('[telegram] per-request cap reached; further notifications suppressed', [
                    'cap' => self::MAX_PER_REQUEST,
                ]);
            }

            return true;
        }

        return false;
    }

    /**
     * Run $fn with notifications disabled.
     *
     * Preferred over Model::withoutEvents(): that would disable every model event, which is
     * fine today but silently breaks the moment someone adds another observer.
     */
    public static function without(callable $fn)
    {
        self::$depth++;
        try {
            return $fn();
        } finally {
            self::$depth--;
        }
    }

    /**
     * Suppress notifications for the remainder of this request, with no matching release.
     *
     * Used at the top of the project-duplication actions. Those methods are 100+ lines of
     * nested copy loops and the entire request exists to clone rows, so a one-line switch at
     * the entry point is both exact and far less risky than wrapping the body in a closure.
     */
    public static function suppressForRequest(): void
    {
        self::$depth++;
    }

    /** Called by telegram:* artisan commands only. */
    public static function allowCli(): void
    {
        self::$cliAllowed = true;
    }

    public static function count(int $n = 1): void
    {
        self::$enqueued += $n;
    }

    public static function enqueuedCount(): int
    {
        return self::$enqueued;
    }

    /** Test helper. */
    public static function reset(): void
    {
        self::$depth = 0;
        self::$enqueued = 0;
        self::$cliAllowed = false;
        self::$capWarned = false;
    }
}
