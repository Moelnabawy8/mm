<?php

namespace App\Services\Storage;

use Google\Client as GoogleClient;
use Google\Service\Drive as GoogleDrive;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\Log;

class GoogleDriveStorageService implements StorageInterface
{
    protected ?GoogleDrive $driveService = null;
    protected string $folderId;

    public function __construct()
    {
        $this->folderId = config('securestream.google_drive.folder_id', '');
    }

    protected function getDriveService(): GoogleDrive
    {
        if ($this->driveService === null) {
            $client = new GoogleClient();
            $client->setClientId(config('securestream.google_drive.client_id'));
            $client->setClientSecret(config('securestream.google_drive.client_secret'));
            $client->refreshToken(config('securestream.google_drive.refresh_token'));
            $client->addScope(GoogleDrive::DRIVE);

            $this->driveService = new GoogleDrive($client);
        }

        return $this->driveService;
    }

    public function upload(string $localPath, string $remotePath): bool
    {
        try {
            $service = $this->getDriveService();
            $fileName = basename($remotePath);

            $folderId = $this->ensureFolderStructure(dirname($remotePath));

            $fileMetadata = new DriveFile([
                'name' => $fileName,
                'parents' => [$folderId],
            ]);

            $content = file_get_contents($localPath);
            if ($content === false) {
                return false;
            }

            $mimeType = mime_content_type($localPath) ?: 'application/octet-stream';

            $service->files->create($fileMetadata, [
                'data' => $content,
                'mimeType' => $mimeType,
                'uploadType' => 'multipart',
                'fields' => 'id',
            ]);

            return true;
        } catch (\Google\Service\Exception $e) {
            if ($this->isQuotaExceeded($e)) {
                Log::warning('Google Drive quota exceeded during upload', [
                    'path' => $remotePath,
                    'error' => $e->getMessage(),
                ]);
                throw new \App\Exceptions\StorageQuotaExceededException(
                    'Google Drive quota exceeded',
                    0,
                    $e
                );
            }
            Log::error('Google Drive upload failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function download(string $remotePath): ?string
    {
        try {
            $fileId = $this->findFileId($remotePath);
            if (!$fileId) {
                return null;
            }

            $service = $this->getDriveService();
            $response = $service->files->get($fileId, ['alt' => 'media']);

            $tempPath = storage_path('app/temp/' . uniqid() . '_' . basename($remotePath));
            if (!is_dir(dirname($tempPath))) {
                mkdir(dirname($tempPath), 0755, true);
            }

            file_put_contents($tempPath, $response->getBody()->getContents());

            return $tempPath;
        } catch (\Exception $e) {
            Log::error('Google Drive download failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function delete(string $remotePath): bool
    {
        try {
            $fileId = $this->findFileId($remotePath);
            if (!$fileId) {
                return false;
            }

            $this->getDriveService()->files->delete($fileId);
            return true;
        } catch (\Exception $e) {
            Log::error('Google Drive delete failed', ['error' => $e->getMessage()]);
            return false;
        }
    }

    public function exists(string $remotePath): bool
    {
        return $this->findFileId($remotePath) !== null;
    }

    public function stream(string $remotePath): mixed
    {
        try {
            $fileId = $this->findFileId($remotePath);
            if (!$fileId) {
                return null;
            }

            $service = $this->getDriveService();
            $response = $service->files->get($fileId, ['alt' => 'media']);

            return $response->getBody();
        } catch (\Exception $e) {
            Log::error('Google Drive stream failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function getDriver(): string
    {
        return 'google';
    }

    protected function findFileId(string $remotePath): ?string
    {
        try {
            $service = $this->getDriveService();
            $fileName = basename($remotePath);
            $parentId = $this->resolveFolderId(dirname($remotePath));

            if (!$parentId) {
                return null;
            }

            $query = sprintf(
                "name='%s' and '%s' in parents and trashed=false",
                addslashes($fileName),
                $parentId
            );

            $results = $service->files->listFiles([
                'q' => $query,
                'fields' => 'files(id)',
                'pageSize' => 1,
            ]);

            $files = $results->getFiles();
            return count($files) > 0 ? $files[0]->getId() : null;
        } catch (\Exception $e) {
            Log::error('Google Drive findFileId failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    protected function ensureFolderStructure(string $path): string
    {
        if ($path === '.' || $path === '' || $path === '/') {
            return $this->folderId;
        }

        $parts = array_filter(explode('/', trim($path, '/')));
        $currentParent = $this->folderId;

        foreach ($parts as $folderName) {
            $existingId = $this->findFolderInParent($folderName, $currentParent);
            if ($existingId) {
                $currentParent = $existingId;
                continue;
            }

            $folderMetadata = new DriveFile([
                'name' => $folderName,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents' => [$currentParent],
            ]);

            $folder = $this->getDriveService()->files->create($folderMetadata, [
                'fields' => 'id',
            ]);

            $currentParent = $folder->getId();
        }

        return $currentParent;
    }

    protected function resolveFolderId(string $path): ?string
    {
        if ($path === '.' || $path === '' || $path === '/') {
            return $this->folderId;
        }

        $parts = array_filter(explode('/', trim($path, '/')));
        $currentParent = $this->folderId;

        foreach ($parts as $folderName) {
            $folderId = $this->findFolderInParent($folderName, $currentParent);
            if (!$folderId) {
                return null;
            }
            $currentParent = $folderId;
        }

        return $currentParent;
    }

    protected function findFolderInParent(string $folderName, string $parentId): ?string
    {
        $query = sprintf(
            "name='%s' and '%s' in parents and mimeType='application/vnd.google-apps.folder' and trashed=false",
            addslashes($folderName),
            $parentId
        );

        $results = $this->getDriveService()->files->listFiles([
            'q' => $query,
            'fields' => 'files(id)',
            'pageSize' => 1,
        ]);

        $files = $results->getFiles();
        return count($files) > 0 ? $files[0]->getId() : null;
    }

    protected function isQuotaExceeded(\Google\Service\Exception $e): bool
    {
        $errors = $e->getErrors();
        foreach ($errors as $error) {
            if (isset($error['reason']) && in_array($error['reason'], [
                'storageQuotaExceeded',
                'userRateLimitExceeded',
                'rateLimitExceeded',
                'dailyLimitExceeded',
            ])) {
                return true;
            }
        }
        return $e->getCode() === 403 || $e->getCode() === 429;
    }
}
