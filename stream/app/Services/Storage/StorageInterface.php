<?php

namespace App\Services\Storage;

interface StorageInterface
{
    public function upload(string $localPath, string $remotePath): bool;

    public function download(string $remotePath): ?string;

    public function delete(string $remotePath): bool;

    public function exists(string $remotePath): bool;

    public function stream(string $remotePath): mixed;

    public function getDriver(): string;
}
