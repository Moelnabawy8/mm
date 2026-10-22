<?php

/**
 * audit_task6_chatbot — what production already has for an AI assistant.
 *
 * READ ONLY. Nothing is written. The OpenAI key is never printed, only its presence
 * and length.
 *
 * The repo carries an `ai-chatbot/` Node service and a Guide-module view that embeds it
 * as <iframe src="http://localhost:3001">. That iframe is resolved by the VISITOR's
 * browser, so it points at each user's own machine and can never reach the server; the
 * service also wants PostgreSQL and Redis while this ERP runs MariaDB. Meanwhile
 * AiTemplateController already calls OpenAI through orhanerday/open-ai using a key in
 * the settings table — which is the plumbing a Laravel-native assistant should reuse.
 *
 * This checks which of that is actually true on the server before anything is built.
 *
 *   php _audit/audit_task6_chatbot.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

function head(string $t): void
{
    echo "\n" . str_repeat('=', 74) . "\n  {$t}\n" . str_repeat('=', 74) . "\n";
}

head('1. IS THERE AN OPENAI KEY, AND WHICH MODEL');

if (!Schema::hasTable('settings')) {
    echo "  no settings table\n";
} else {
    foreach (['chat_gpt_key', 'chat_gpt_model', 'openai_key', 'ai_key'] as $name) {
        $rows = DB::table('settings')->where('name', $name)->get();

        if ($rows->isEmpty()) {
            printf("  %-18s (not set)\n", $name);
            continue;
        }

        foreach ($rows as $r) {
            $v = (string) ($r->value ?? '');

            // Never print a credential. Length and shape are enough to judge it.
            if ($name === 'chat_gpt_key' || str_contains($name, 'key')) {
                printf("  %-18s set for created_by=%s, %d chars, starts %s\n",
                    $name,
                    $r->created_by ?? '?',
                    strlen($v),
                    $v === '' ? '(empty)' : substr($v, 0, 3) . '…');
            } else {
                printf("  %-18s %s (created_by=%s)\n", $name, $v === '' ? '(empty)' : $v, $r->created_by ?? '?');
            }
        }
    }
}

echo "\n  orhanerday/open-ai installed: "
   . (class_exists(\Orhanerday\OpenAi\OpenAi::class) ? "yes\n" : "NO\n");

head('2. THE EXISTING CHATBOT — is any of it reachable?');

$names = [];

foreach (Route::getRoutes() as $r) {
    if ($r->getName() && preg_match('/chatbot|guide|ai[-_.]/i', $r->getName())) {
        $names[$r->getName()] = sprintf('  %-8s %-44s %s', implode('|', $r->methods()), $r->uri(), $r->getName());
    }
}

ksort($names);
echo $names ? implode("\n", $names) . "\n" : "  none\n";

$iframe = __DIR__ . '/../Modules/Guide/Resources/views/chatbot/index.blade.php';

echo "\n  Guide chatbot view: " . (is_file($iframe) ? 'present' : 'missing') . "\n";

if (is_file($iframe)) {
    foreach (file($iframe) as $i => $line) {
        if (stripos($line, 'iframe') !== false || stripos($line, 'localhost') !== false) {
            printf("    line %-4d %s\n", $i + 1, trim(mb_strimwidth($line, 0, 100, '…')));
        }
    }
}

echo "\n  ai-chatbot/ node service on the server: "
   . (is_dir(__DIR__ . '/../ai-chatbot') ? "present\n" : "not deployed\n");

if (is_dir(__DIR__ . '/../ai-chatbot')) {
    echo "    (it wants PostgreSQL + Redis + a long-running node process;\n";
    echo "     and its iframe URL resolves in the visitor's browser, not here)\n";
}

head('3. WHAT AN ASSISTANT COULD ACTUALLY ANSWER FROM');

$tables = [
    'project_tasks'          => 'tasks',
    'projects'               => 'projects',
    'departments'            => 'departments',
    'employees'              => 'employees',
    'users'                  => 'users',
    'commitments'            => 'commitments (new)',
    'task_quality_reviews'   => 'quality reviews (new)',
    'quality_criteria'       => 'quality criteria (new)',
    'meetings'               => 'meetings',
    'leads'                  => 'leads',
    'deals'                  => 'deals',
    'quality_evaluations'    => 'lead evaluations',
    'attendance_employees'   => 'attendance',
    'inactivity_ban_logs'    => 'inactivity log (new)',
];

foreach ($tables as $t => $label) {
    if (!Schema::hasTable($t)) {
        printf("  %-24s (table missing)\n", $label);
        continue;
    }

    printf("  %-24s %s rows\n", $label, number_format(DB::table($t)->count()));
}

head('4. COST CONTROL — what already limits AI usage');

foreach (['ai_monthly_limit', 'chat_gpt_limit', 'ai_enabled'] as $name) {
    $v = Schema::hasTable('settings')
        ? DB::table('settings')->where('name', $name)->value('value')
        : null;

    printf("  %-20s %s\n", $name, $v === null ? '(not set)' : $v);
}

echo "\n  (no limit setting exists, so a new assistant has to bring its own —\n";
echo "   an unbounded chat box against a paid API is a bill nobody agreed to)\n";

head('5. PERMISSIONS ALREADY IN PLAY');

if (Schema::hasTable('permissions')) {
    $perms = DB::table('permissions')
        ->where(function ($q) {
            foreach (['ai', 'chat', 'guide'] as $w) {
                $q->orWhere('name', 'like', "%{$w}%");
            }
        })
        ->orderBy('name')
        ->pluck('name');

    echo $perms->isEmpty() ? "  none\n" : '  ' . $perms->implode("\n  ") . "\n";
}

echo "\n" . str_repeat('=', 74) . "\n  done — nothing was written\n" . str_repeat('=', 74) . "\n";
