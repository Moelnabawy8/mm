<?php

/**
 * fix_meeting_date_cast — undo a cast that broke the meeting calendar.
 *
 * install_commitments_module.php added `'date' => 'date'` to the Meeting model's casts.
 * That was a convenience for the new minutes screens and it broke an existing one:
 * MeetingController's calendar feed builds each event as
 *
 *     "start" => $val->date . ' ' . $val->time
 *
 * With the cast, `$val->date` is a Carbon whose __toString is 'Y-m-d H:i:s', so the
 * concatenation produced "2026-09-20 00:00:00 11:00" and FullCalendar could not parse
 * it. The calendar is part of a module that has worked since 2020; a new feature does
 * not get to change how an old one behaves.
 *
 * So the cast goes, and the new code asks for a date object explicitly via dateOn().
 *
 * Also updates the two minutes views, which were calling $meeting->date->format() and
 * relied on the cast being there.
 *
 *   php _fix/fix_meeting_date_cast.php --dry-run
 *   php _fix/fix_meeting_date_cast.php
 */

$dry  = in_array('--dry-run', $argv, true);
$root = __DIR__ . '/..';

$patches = [
    [
        'file'  => $root . '/app/Models/Meeting.php',
        'label' => 'Meeting model — drop the date cast, add dateOn()',
        'probe' => 'public function dateOn()',
        'from'  => "    protected \$casts = [\n        'date'       => 'date',\n        'minutes_at' => 'datetime',\n    ];",
        'to'    => "    /**\n"
                 . "     * `date` is deliberately NOT cast.\n"
                 . "     *\n"
                 . "     * MeetingController's calendar feed builds its event start as\n"
                 . "     * `\$val->date . ' ' . \$val->time`. Casting `date` turns that into a Carbon whose\n"
                 . "     * __toString is 'Y-m-d H:i:s', so the concatenation produced\n"
                 . "     * \"2026-09-20 00:00:00 11:00\" and FullCalendar could not parse it — a working\n"
                 . "     * screen broken by a convenience on a model shared since 2020. Code that wants a\n"
                 . "     * date object calls dateOn() instead.\n"
                 . "     */\n"
                 . "    protected \$casts = [\n        'minutes_at' => 'datetime',\n    ];\n"
                 . "\n"
                 . "    /** The meeting date as a Carbon, or null when it has none. */\n"
                 . "    public function dateOn(): ?\\Carbon\\Carbon\n"
                 . "    {\n"
                 . "        if (empty(\$this->date)) {\n"
                 . "            return null;\n"
                 . "        }\n"
                 . "\n"
                 . "        try {\n"
                 . "            return \\Carbon\\Carbon::parse(\$this->date);\n"
                 . "        } catch (\\Throwable \$e) {\n"
                 . "            return null;\n"
                 . "        }\n"
                 . "    }",
        'lint'  => true,
    ],
    [
        'file'  => $root . '/app/Models/Meeting.php',
        'label' => 'Meeting model — isAwaitingMinutes stops relying on the cast',
        'probe' => '$on = $this->dateOn();',
        'from'  => "        return \$this->date !== null && \$this->date->endOfDay()->isPast();",
        'to'    => "        \$on = \$this->dateOn();\n\n        return \$on !== null && \$on->endOfDay()->isPast();",
        'lint'  => true,
    ],
    [
        'file'  => $root . '/resources/views/meetings/minutes_board.blade.php',
        'label' => 'minutes board — awaiting list',
        'probe' => '@php $on = $m->dateOn(); @endphp',
        'from'  => "                                            {{ \$m->date ? \$m->date->format('Y-m-d') : '—' }}\n"
                 . "                                            <span class=\"text-danger d-block\">\n"
                 . "                                                {{ \$m->date ? \$m->date->diffInDays(now()) : 0 }} {{ __('days ago') }}",
        'to'    => "                                            @php \$on = \$m->dateOn(); @endphp\n"
                 . "                                            {{ \$on ? \$on->format('Y-m-d') : '—' }}\n"
                 . "                                            <span class=\"text-danger d-block\">\n"
                 . "                                                {{ \$on ? (int) \$on->diffInDays(now()) : 0 }} {{ __('days ago') }}",
        'lint'  => false,
    ],
    [
        'file'  => $root . '/resources/views/meetings/minutes_board.blade.php',
        'label' => 'minutes board — upcoming list',
        'probe' => "optional(\$m->dateOn())->format('Y-m-d')",
        'from'  => "{{ \$m->date ? \$m->date->format('Y-m-d') : '—' }} {{ \$m->time }}",
        'to'    => "{{ optional(\$m->dateOn())->format('Y-m-d') ?: '—' }} {{ \$m->time }}",
        'lint'  => false,
    ],
    [
        'file'  => $root . '/resources/views/meetings/minutes_edit.blade.php',
        'label' => 'minutes edit — header date',
        'probe' => "optional(\$meeting->dateOn())->format('Y-m-d')",
        'from'  => "{{ \$meeting->date ? \$meeting->date->format('Y-m-d') : '—' }} {{ \$meeting->time }}",
        'to'    => "{{ optional(\$meeting->dateOn())->format('Y-m-d') ?: '—' }} {{ \$meeting->time }}",
        'lint'  => false,
    ],
];

$applied = 0;

foreach ($patches as $p) {
    if (!is_file($p['file'])) {
        fwrite(STDERR, "  missing file: {$p['file']}\n");
        exit(1);
    }

    $raw = file_get_contents($p['file']);
    $eol = strpos($raw, "\r\n") !== false ? "\r\n" : "\n";
    $src = str_replace("\r\n", "\n", $raw);

    if (strpos($src, $p['probe']) !== false) {
        echo "  already fixed    {$p['label']}\n";
        continue;
    }

    $hits = substr_count($src, $p['from']);

    if ($hits !== 1) {
        fwrite(STDERR, "  ANCHOR {$hits}x (need exactly 1) for {$p['label']} — refusing.\n");
        exit(1);
    }

    $src = str_replace($p['from'], $p['to'], $src);
    echo "  patched          {$p['label']}\n";
    $applied++;

    if ($dry) {
        continue;
    }

    $out = str_replace("\n", $eol, $src);

    if ($p['lint']) {
        $tmp = sys_get_temp_dir() . '/mc_' . getmypid() . '_' . mt_rand() . '.php';
        file_put_contents($tmp, $out);
        exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lines, $rc);
        unlink($tmp);

        if ($rc !== 0) {
            fwrite(STDERR, "  PHP SYNTAX ERROR — nothing written:\n  " . implode("\n  ", $lines) . "\n");
            exit(1);
        }
    }

    copy($p['file'], $p['file'] . '.bak-' . date('Ymd-His'));
    file_put_contents($p['file'], $out);
    clearstatcache();
}

echo "\n";

if (!$applied) {
    echo "Nothing to do.\n";
    exit(0);
}

echo $dry
    ? "dry run — nothing written.\n"
    : "Now run:\n  php artisan optimize:clear\n\nThen check the meeting calendar renders: /meeting-calender\n";
