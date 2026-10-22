<?php
// Bootstrap Laravel to get real public_path()
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

header('Content-Type: application/json');

$pub  = public_path();
$base = base_path();
$testFile = 'uploads/leave_attachments/29/1776513839_z7aJVDZN9c.png';

echo json_encode([
    'base_path'         => $base,
    'public_path'       => $pub,
    'script_dir'        => __DIR__,
    'public_matches_scriptdir' => (realpath($pub) === realpath(__DIR__)),
    'file_exists_public'=> file_exists($pub  . '/' . $testFile),
    'file_exists_base'  => file_exists($base . '/' . $testFile),
    'file_exists_scriptdir' => file_exists(__DIR__ . '/' . $testFile),
    'uploads_exists_public' => is_dir($pub  . '/uploads'),
    'uploads_exists_scriptdir' => is_dir(__DIR__ . '/uploads'),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
