<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

header('Content-Type: application/json');

use Illuminate\Support\Facades\Storage;

$base = base_path();
$pub  = public_path();

// Find a real deal_purchase_attachment file
$storagePubPath = storage_path('app/public');
$attachDir = $storagePubPath . '/deal_purchase_attachments';
$realFile = null;
$realFilePath = null;
if (is_dir($attachDir)) {
    $rit = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($attachDir, FilesystemIterator::SKIP_DOTS));
    foreach ($rit as $f) {
        if ($f->isFile()) {
            $realFile = str_replace($storagePubPath . '/', '', $f->getPathname());
            $realFilePath = $f->getPathname();
            break;
        }
    }
}

echo json_encode([
    'base_path'          => $base,
    'public_path'        => $pub,
    'storage_path'       => storage_path(),
    'storage_app_public' => $storagePubPath,
    'storage_disk_url'   => Storage::disk('public')->url('deal_purchase_attachments/test/file.jpg'),
    'app_url'            => config('app.url'),
    'real_file_relative' => $realFile,
    'real_file_absolute' => $realFilePath,
    'symlink_exists'     => [
        'public_html/storage'         => is_dir($base . '/storage') && is_link($base . '/storage'),
        'public_html/public/storage'  => file_exists($pub . '/storage'),
        'is_link_public_storage'      => is_link($pub . '/storage'),
        'link_target'                 => is_link($pub . '/storage') ? readlink($pub . '/storage') : 'not a symlink',
    ],
    'accessible_urls'    => [
        'via_storage'        => config('app.url') . '/storage/' . $realFile,
        'via_public_storage' => config('app.url') . '/public/storage/' . $realFile,
    ],
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
