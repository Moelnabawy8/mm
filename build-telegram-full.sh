#!/usr/bin/env bash
# Builds telegram-full-files.zip from the SERVER copies in server-copy/.
#   bash build-telegram-full.sh
# Output: telegram-full-files.zip (extract in public_html) + telegram-diff-report.txt
set -euo pipefail
cd "$(dirname "$0")"

SCR="${SCR:-/c/Users/ENG_MO~1/AppData/Local/Temp/claude/c--xampp-htdocs-mm/61459be9-af5c-45c4-8d53-66b3271a2b4f/scratchpad}"
PATCHER="$SCR/tg_patch.php"
OUT="$SCR/full"
SHARED="routes/web.php routes/console.php app/Observers/ProjectTaskObserver.php app/Observers/TaskCommentObserver.php resources/views/settings/company.blade.php resources/views/partials/admin/menu.blade.php"
NEW="app/Console/Commands/TelegramDispatch.php app/Console/Commands/TelegramTaskReminders.php
app/Http/Controllers/TelegramController.php app/Models/TelegramMessage.php
app/Services/Telegram/TelegramClient.php app/Support/Telegram/TelegramConfig.php
app/Support/Telegram/TelegramGate.php app/Support/Telegram/TelegramNotifier.php
database/migrations/2026_09_27_120001_create_telegram_messages_table.php
database/migrations/2026_09_27_120002_create_telegram_link_tokens_table.php
database/migrations/2026_09_27_120003_add_telegram_to_users_table.php
database/migrations/2026_09_27_120004_add_telegram_permissions.php
docs/telegram-task-notifications.md
resources/views/telegram/link.blade.php resources/views/telegram/log.blade.php
resources/views/telegram/recipients.blade.php resources/views/telegram/partials/settings.blade.php"

for f in $SHARED; do [ -f "server-copy/$f" ] || { echo "MISSING server-copy/$f"; exit 1; }; done
rm -rf "$OUT"; mkdir -p "$OUT"

# 1. patch the server copies (aborts on any missing/ambiguous anchor)
php "$PATCHER" server-copy "$OUT"

# 2. lint + round-trip proof (reverse must give back the server copy byte-for-byte)
for f in $SHARED; do case "$f" in *.blade.php) ;; *.php) php -l "$OUT/$f" >/dev/null || exit 1;; esac; done
rm -rf "$OUT.rev"; php "$PATCHER" "$OUT" "$OUT.rev" --reverse >/dev/null
diff -r server-copy "$OUT.rev" >/dev/null || { echo "ROUND-TRIP FAILED"; exit 1; }

# 3. diff report: every changed line must be an addition
{
  echo "Telegram patch vs server-copy — $(date)"; echo
  for f in $SHARED; do
    add=$(diff "server-copy/$f" "$OUT/$f" | grep -c '^>' || true)
    del=$(diff "server-copy/$f" "$OUT/$f" | grep -c '^<' || true)
    echo "$f   +$add  -$del"
  done
  echo
  for f in $SHARED; do diff -u "server-copy/$f" "$OUT/$f" --label "server/$f" --label "patched/$f" || true; echo; done
} > telegram-diff-report.txt
if diff -r server-copy "$OUT" | grep -q '^<'; then echo "REMOVED LINES FOUND — not shipping"; exit 1; fi

# 4. zip with forward slashes (Compress-Archive writes backslashes → broken on Linux)
for f in $NEW; do mkdir -p "$OUT/$(dirname "$f")"; cp "$f" "$OUT/$f"; done
php -r '
$out=$argv[1]; $z=new ZipArchive; @unlink("telegram-full-files.zip");
$z->open("telegram-full-files.zip", ZipArchive::CREATE);
$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($out, FilesystemIterator::SKIP_DOTS));
foreach($it as $f){ $rel=str_replace("\\","/",substr($f->getPathname(), strlen($out)+1)); $z->addFile($f->getPathname(), $rel); }
$z->close();' "$OUT"
unzip -l telegram-full-files.zip | grep -q '\\' && { echo "BACKSLASH IN ZIP"; exit 1; }
unzip -l telegram-full-files.zip
echo; head -8 telegram-diff-report.txt
echo "OK — round-trip identical, additions only, zip paths use '/'"
