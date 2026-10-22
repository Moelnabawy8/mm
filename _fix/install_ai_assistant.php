<?php

/**
 * install_ai_assistant — give the existing chatbot access to the ERP's own data,
 * a permission, and a spend limit.
 *
 * The chatbot already exists and works: ChatController sends a long hand-written system
 * prompt to OpenAI and returns the reply. What it cannot do is answer a single question
 * about what is actually in the system — "how many tasks are late in Design" got a tour
 * of the menu. This wires it to App\Services\Ai\AiAssistant, which lets the model call
 * read-only lookups and answer from real figures.
 *
 * Three defects in the existing code are fixed on the way:
 *
 *   - `env('OPENAI_API_KEY')` read at request time. env() returns null once config is
 *     cached, so the feature could die on a deploy that changed nothing about it.
 *   - The model was hardcoded to gpt-4o-mini while the settings table said gpt-4o.
 *   - The routes were gated on `auth` alone: all 124 accounts could spend against a
 *     paid API with no limit and no record.
 *
 * ChatController.php and routes/web.php are shared files, so both are edited by exact
 * anchor and the script refuses to run if an anchor is missing or ambiguous — a deploy
 * that shipped a whole copy of a shared file took this site down on 2026-09-12.
 *
 *   php _fix/install_ai_assistant.php --dry-run
 *   php _fix/install_ai_assistant.php
 */

$dry  = in_array('--dry-run', $argv, true);
$root = __DIR__ . '/..';

/* ------------------------------------------------- 1. ChatController::send() */

$oldSend = <<<'OLD'
            $systemPrompt = [
                'role' => 'system',
                'content' => $this->getSystemPrompt()
            ];

            $messages = array_merge([$systemPrompt], array_values($conversationHistory));

            $apiKey = env('OPENAI_API_KEY');

            if (empty($apiKey)) {
                return response()->json([
                    'success' => false,
                    'message' => 'OpenAI API key is not configured. Please add OPENAI_API_KEY to your .env file.'
                ], 500);
            }

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ])->timeout(60)->post('https://api.openai.com/v1/chat/completions', [
                'model' => 'gpt-4o-mini',
                'messages' => $messages,
                'max_tokens' => 1000,
                'temperature' => 0.7,
            ]);

            if ($response->failed()) {
                $errorMessage = 'OpenAI API request failed.';
                if ($response->status() === 401) {
                    $errorMessage = 'Invalid OpenAI API key.';
                } elseif ($response->status() === 429) {
                    $errorMessage = 'OpenAI API rate limit exceeded.';
                }

                return response()->json([
                    'success' => false,
                    'message' => $errorMessage
                ], $response->status());
            }

            $data = $response->json();

            if (!isset($data['choices'][0]['message']['content'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid response from OpenAI API.'
                ], 500);
            }

            $botMessage = $data['choices'][0]['message']['content'];
OLD;

$newSend = <<<'NEW'
            // The lookups, the key resolution, the model choice and the monthly cap all
            // live in AiAssistant so the controller stays a controller. The history
            // excludes the message just stored, which ask() adds as the final turn.
            $priorTurns = array_slice(array_values($conversationHistory), 0, -1);

            $answer = app(\App\Services\Ai\AiAssistant::class)->ask($userMessage, $priorTurns);

            if (!$answer['ok']) {
                // 200, not 500: "you are out of questions this month" and "no key is
                // configured" are answers, not server faults, and the chat box shows
                // them to the user either way.
                return response()->json([
                    'success' => false,
                    'message' => $answer['message'],
                ], 200);
            }

            $botMessage = $answer['message'];

            if ($botMessage === '') {
                return response()->json([
                    'success' => false,
                    'message' => __('The assistant had nothing to say. Try rephrasing.'),
                ], 200);
            }
NEW;

/* ------------------------------------------------------ 2. permission gates */

$oldIndex = <<<'OLD'
    public function index()
    {
        $messages = ChatMessage::forUser(Auth::id())
OLD;

$newIndex = <<<'NEW'
    /** Everything here needs the permission; the routes only ever checked auth. */
    private function denyUnlessAllowed()
    {
        if (!Auth::check() || !Auth::user()->can('use ai assistant')) {
            return redirect()->route('dashboard')->with('error', __('Permission Denied.'));
        }

        return null;
    }

    public function index()
    {
        if ($deny = $this->denyUnlessAllowed()) {
            return $deny;
        }

        $messages = ChatMessage::forUser(Auth::id())
NEW;

$oldSendHead = <<<'OLD'
    public function send(Request $request)
    {
        $validator = Validator::make($request->all(), [
OLD;

$newSendHead = <<<'NEW'
    public function send(Request $request)
    {
        if (!Auth::check() || !Auth::user()->can('use ai assistant')) {
            return response()->json([
                'success' => false,
                'message' => __('Permission Denied.'),
            ], 403);
        }

        $validator = Validator::make($request->all(), [
NEW;


/* ------------------------------------------------------------------ patches */

$patches = [
    [
        'file'  => $root . '/app/Http/Controllers/ChatController.php',
        'label' => 'send() — answer from ERP data via AiAssistant',
        'probe' => 'App\\Services\\Ai\\AiAssistant',
        'from'  => $oldSend,
        'to'    => $newSend,
    ],
    [
        'file'  => $root . '/app/Http/Controllers/ChatController.php',
        'label' => 'index() — require the permission',
        'probe' => 'denyUnlessAllowed',
        'from'  => $oldIndex,
        'to'    => $newIndex,
    ],
    [
        'file'  => $root . '/app/Http/Controllers/ChatController.php',
        'label' => 'send() — require the permission',
        'probe' => "can('use ai assistant')",
        'from'  => $oldSendHead,
        'to'    => $newSendHead,
        'skipIfProbeCount' => 2,   // index() adds one occurrence of its own
    ],
];

$applied = 0;

foreach ($patches as $p) {
    $raw = file_get_contents($p['file']);
    $eol = strpos($raw, "\r\n") !== false ? "\r\n" : "\n";
    $src = str_replace("\r\n", "\n", $raw);

    $probeCount = substr_count($src, $p['probe']);
    $enough     = $p['skipIfProbeCount'] ?? 1;

    if ($probeCount >= $enough) {
        echo "  already present  {$p['label']}\n";
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

    $tmp = sys_get_temp_dir() . '/ai_' . getmypid() . '_' . mt_rand() . '.php';
    file_put_contents($tmp, $out);
    exec('php -l ' . escapeshellarg($tmp) . ' 2>&1', $lines, $rc);
    unlink($tmp);

    if ($rc !== 0) {
        fwrite(STDERR, "  PHP SYNTAX ERROR — nothing written:\n  " . implode("\n  ", $lines) . "\n");
        exit(1);
    }

    copy($p['file'], $p['file'] . '.bak-' . date('Ymd-His'));
    file_put_contents($p['file'], $out);
    clearstatcache();
}

/* ---------------------------------------------------------------- 3. settings */

if (!$dry) {
    // Booting the app to write two settings rows must never undo the file edits above.
    // An uncaught QueryException here killed the script after it had already patched the
    // controller, which reads as a failed install when it was a successful one.
    try {
        require $root . '/vendor/autoload.php';
        $app = require_once $root . '/bootstrap/app.php';
        $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

        if (\Illuminate\Support\Facades\Schema::hasTable('settings')) {
            foreach (['ai_enabled' => 'on', 'ai_monthly_limit' => '100'] as $name => $value) {
                $exists = \Illuminate\Support\Facades\DB::table('settings')->where('name', $name)->exists();

                if ($exists) {
                    echo "  setting present  {$name}\n";
                    continue;
                }

                \Illuminate\Support\Facades\DB::table('settings')->insert([
                    'name'       => $name,
                    'value'      => $value,
                    'created_by' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                echo "  setting created  {$name} = {$value}\n";
                $applied++;
            }
        }
    } catch (\Throwable $e) {
        echo "  settings step SKIPPED — the database was not reachable:\n";
        echo '    ' . explode("\n", $e->getMessage())[0] . "\n";
        echo "    The file changes above are applied. Set ai_enabled=on and\n";
        echo "    ai_monthly_limit=100 in the settings table, or re-run this script.\n";
    }
}

echo "\n";

if (!$applied) {
    echo "Nothing to do.\n";
    exit(0);
}

echo $dry
    ? "dry run — nothing written.\n"
    : "Now run:\n  php artisan migrate --force\n  php artisan optimize:clear\n";
