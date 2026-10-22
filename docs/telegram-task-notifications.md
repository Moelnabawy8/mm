# Telegram Task Notifications — build notes (2026-09-27)

Companion to the WhatsApp integration. `TelegramNotifier` is FULLY INDEPENDENT from
`TaskNotifier` — no shared classes, no shared helpers. Read this before touching either.

## Architecture

- `telegram_messages` — outbox + audit log (separate table, NOT a `channel` column, so the
  /log page, permissions and filters are reused 1:1 without disturbing WhatsApp).
- `telegram_link_tokens` — one-time 32-char tokens (15-min TTL, single-use). Safer than an
  encrypted user_id: unguessable, non-replayable, fits Telegram's 64-char `start` limit.
- `users.telegram_chat_id` + `users.telegram_status` — NOT in `$fillable` (same rule as
  `whatsapp`: `UserController@editprofile` uses `fill($input)->save()`, which would let an
  employee hijack someone else's notifications).
- `telegram:dispatch` (every minute) — getUpdates long-poll (stored offset in settings) for
  /start linking + outbox drain with claim/backoff/daily-cap. `telegram:task-reminders`
  (daily 08:00) — due today/tomorrow only, NEVER overdue (same ~1169-task hazard as WA).
- NO webhook, NO public endpoint, NO CSRF exception. Bot API has no delivery receipts —
  `delivered_at`/`read_at` stay NULL; rows go pending → sending → sent/failed.

## Traps discovered (do not re-learn)

1. `app/Console/Kernel.php` is DEAD CODE on Laravel 11 — schedule in `routes/console.php`
   only. Verified with `php artisan schedule:list`.
2. `FilterRequest` middleware strips the substrings `alert`/`javascript` from EVERY request
   value (this once corrupted the WA template `task_alert_ar` → `task__ar`). The bot token
   is read from raw `$_POST` in `TelegramController@saveSettings` — never "fix" that to
   `$request->input()`.
3. `Utility::settings()` returns the super-admin (`created_by=1`) rows in CLI. `TelegramConfig`
   reads `settings` directly with `created_by=2` — same as `WhatsAppConfig`.
4. `whatsapp:dispatch` runs from SYSTEM crontab (`crontab -u erpmy5243`), not the Laravel
   scheduler. `telegram:dispatch` is registered in `routes/console.php`, so the server MUST
   have `schedule:run` every minute in erpmy5243's crontab — otherwise it silently never runs
   (same trap as `inactivity:weekly-run` before 2026-08-15). Fallback: direct cron line
   `* * * * * /usr/bin/flock -n /tmp/telegram-dispatch.lock php /path/artisan telegram:dispatch`.
5. Run cron as `erpmy5243`, NEVER root (root-owned files in `storage/` 500 the site under lsphp).
6. Project duplication (`ApiController@duplicateProject`) re-saves every task+comment → both
   `WhatsAppGate::suppressForRequest()` AND `TelegramGate::suppressForRequest()` are called
   there. `ProjectController@copyprojectstore` has NO suppression call for either channel
   (pre-existing gap, WhatsApp included) — add both if that path is ever wired up.
7. Bot API error shorthand: 401 = bad token → global pause; 400/403 "chat not found / bot was
   blocked" = dead chat → mark `telegram_status=invalid`; 429 → retryable (honors retry_after).
8. `App\Services\TelegramService` (marketing channel posts) is UNRELATED — do not merge with
   `App\Services\Telegram\TelegramClient`.

## Server checklist (run by the human, no plink without permission)

- [ ] `php artisan migrate` (4 migrations: messages, link_tokens, users columns, permissions)
- [ ] `php artisan schedule:list` shows `telegram:dispatch` every minute
- [ ] `crontab -u erpmy5243 -l` contains `schedule:run` every minute (else add direct line, see 4)
- [ ] Create bot via @BotFather, save token + username in Settings → Telegram, Test connection
- [ ] Keep dry-run ON first, link one account, test-send, watch `telegram/log`, then go live
