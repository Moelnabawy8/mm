<?php

namespace App\Services\Storage;

use Illuminate\Support\Facades\Storage;

class LocalStorageService implements StorageInterface
{
    protected string $disk = 'local';

    public function upload(string $localPath, string $remotePath): bool
    {
        $contents = file_get_contents($localPath);
        if ($contents === false) {
            return false;
        }

        return Storage::disk($this->disk)->put($remotePath, $contents);
    }

    public function download(string $remotePath): ?string
    {
        if (!$this->exists($remotePath)) {
            return null;
        }

        return Storage::disk($this->disk)->path($remotePath);
    }

    public function delete(string $remotePath): bool
    {
        return Storage::disk($this->disk)->delete($remotePath);
    }

    public function exists(string $remotePath): bool
    {
        return Storage::disk($this->disk)->exists($remotePath);
    }

    public function stream(string $remotePath): mixed
    {
        if (!$this->exists($remotePath)) {
            return null;
        }

        return Storage::disk($this->disk)->readStream($remotePath);
    }

    public function getDriver(): string
    {
        return 'local';
    }

    public function getFullPath(string $remotePath): string
    {
        return Storage::disk($this->disk)->path($remotePath);
    }
}
