<?php

namespace Modules\VideoStreaming\Services;

use Google\Client;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

class GoogleDriveService
{
    protected ?Client $client = null;
    protected ?Drive $driveService = null;
    protected string $folderId;

    public function __construct()
    {
        $this->folderId = config('videostreaming.google_drive.folder_id', '');
    }

    /**
     * Initialize Google Client.
     */
    protected function initializeClient(): void
    {
        if ($this->client !== null) {
            return;
        }

        $this->client = new Client();
        $this->client->setClientId(config('videostreaming.google_drive.client_id'));
        $this->client->setClientSecret(config('videostreaming.google_drive.client_secret'));
        $this->client->addScope(Drive::DRIVE);
        $this->client->setAccessType('offline');

        // Set refresh token
        $refreshToken = config('videostreaming.google_drive.refresh_token');
        if ($refreshToken) {
            $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
        }

        $this->driveService = new Drive($this->client);
    }

    /**
     * Upload file to Google Drive.
     */
    public function uploadFile(string $filePath, string $fileName, ?string $folderId = null, string $mimeType = 'video/mp4'): ?string
    {
        try {
            $this->initializeClient();

            $fileMetadata = new DriveFile([
                'name' => $fileName,
                'parents' => [$folderId ?? $this->folderId]
            ]);

            $content = file_get_contents($filePath);

            $file = $this->driveService->files->create($fileMetadata, [
                'data' => $content,
                'mimeType' => $mimeType,
                'uploadType' => 'multipart',
                'fields' => 'id'
            ]);

            return $file->id;
        } catch (Exception $e) {
            Log::error('Google Drive upload failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Upload file in chunks (for large files).
     */
    public function uploadLargeFile(string $filePath, string $fileName, ?string $folderId = null, string $mimeType = 'video/mp4'): ?string
    {
        try {
            $this->initializeClient();

            $fileMetadata = new DriveFile([
                'name' => $fileName,
                'parents' => [$folderId ?? $this->folderId]
            ]);

            $chunkSizeBytes = 5 * 1024 * 1024; // 5MB chunks

            $this->client->setDefer(true);
            $request = $this->driveService->files->create($fileMetadata);

            $media = new \Google\Http\MediaFileUpload(
                $this->client,
                $request,
                $mimeType,
                null,
                true,
                $chunkSizeBytes
            );
            $media->setFileSize(filesize($filePath));

            $status = false;
            $handle = fopen($filePath, "rb");

            while (!$status && !feof($handle)) {
                $chunk = fread($handle, $chunkSizeBytes);
                $status = $media->nextChunk($chunk);
            }

            fclose($handle);
            $this->client->setDefer(false);

            return $status ? $status->id : null;
        } catch (Exception $e) {
            Log::error('Google Drive large upload failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Download file from Google Drive.
     */
    public function downloadFile(string $fileId, string $destinationPath): bool
    {
        try {
            $this->initializeClient();

            $response = $this->driveService->files->get($fileId, [
                'alt' => 'media'
            ]);

            $content = $response->getBody()->getContents();
            file_put_contents($destinationPath, $content);

            return true;
        } catch (Exception $e) {
            Log::error('Google Drive download failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create folder in Google Drive.
     */
    public function createFolder(string $folderName, ?string $parentFolderId = null): ?string
    {
        try {
            $this->initializeClient();

            $fileMetadata = new DriveFile([
                'name' => $folderName,
                'mimeType' => 'application/vnd.google-apps.folder',
                'parents' => [$parentFolderId ?? $this->folderId]
            ]);

            $folder = $this->driveService->files->create($fileMetadata, [
                'fields' => 'id'
            ]);

            return $folder->id;
        } catch (Exception $e) {
            Log::error('Google Drive folder creation failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Delete file from Google Drive.
     */
    public function deleteFile(string $fileId): bool
    {
        try {
            $this->initializeClient();
            $this->driveService->files->delete($fileId);
            return true;
        } catch (Exception $e) {
            Log::error('Google Drive delete failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get file metadata.
     */
    public function getFileMetadata(string $fileId): ?array
    {
        try {
            $this->initializeClient();

            $file = $this->driveService->files->get($fileId, [
                'fields' => 'id,name,mimeType,size,createdTime,modifiedTime'
            ]);

            return [
                'id' => $file->getId(),
                'name' => $file->getName(),
                'mimeType' => $file->getMimeType(),
                'size' => $file->getSize(),
                'createdTime' => $file->getCreatedTime(),
                'modifiedTime' => $file->getModifiedTime(),
            ];
        } catch (Exception $e) {
            Log::error('Google Drive get metadata failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * List files in folder.
     */
    public function listFiles(?string $folderId = null): array
    {
        try {
            $this->initializeClient();

            $query = sprintf("'%s' in parents and trashed = false", $folderId ?? $this->folderId);

            $response = $this->driveService->files->listFiles([
                'q' => $query,
                'fields' => 'files(id,name,mimeType,size,createdTime)',
                'pageSize' => 100
            ]);

            return $response->getFiles();
        } catch (Exception $e) {
            Log::error('Google Drive list files failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get streaming URL (temporary).
     */
    public function getStreamingUrl(string $fileId): ?string
    {
        try {
            $this->initializeClient();

            // Get a temporary download URL
            $file = $this->driveService->files->get($fileId, [
                'fields' => 'webContentLink'
            ]);

            return $file->getWebContentLink();
        } catch (Exception $e) {
            Log::error('Google Drive get streaming URL failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Set file permissions (make private).
     */
    public function setPrivate(string $fileId): bool
    {
        try {
            $this->initializeClient();

            // Get all permissions
            $permissions = $this->driveService->permissions->listPermissions($fileId);

            foreach ($permissions->getPermissions() as $permission) {
                if ($permission->getRole() !== 'owner') {
                    $this->driveService->permissions->delete($fileId, $permission->getId());
                }
            }

            return true;
        } catch (Exception $e) {
            Log::error('Google Drive set private failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get download stream for file.
     */
    public function getFileStream(string $fileId)
    {
        try {
            $this->initializeClient();

            $response = $this->driveService->files->get($fileId, [
                'alt' => 'media'
            ]);

            return $response->getBody();
        } catch (Exception $e) {
            Log::error('Google Drive get stream failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Download file with range request support (streaming-friendly).
     */
    public function downloadFileWithRange(string $fileId, ?int $start = null, ?int $end = null): ?string
    {
        try {
            $this->initializeClient();

            $httpClient = $this->client->authorize();
            $url = 'https://www.googleapis.com/drive/v3/files/' . $fileId . '?alt=media';

            $headers = [];
            if ($start !== null || $end !== null) {
                $rangeHeader = 'bytes=';
                $rangeHeader .= ($start !== null) ? $start : '0';
                $rangeHeader .= '-';
                $rangeHeader .= ($end !== null) ? $end : '';
                $headers['Range'] = $rangeHeader;
            }

            $response = $httpClient->request('GET', $url, ['headers' => $headers]);

            if ($response->getStatusCode() === 200 || $response->getStatusCode() === 206) {
                return $response->getBody()->getContents();
            }

            return null;
        } catch (Exception $e) {
            Log::error('Google Drive range download failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get file by name from folder (cached lookup).
     */
    public function getFileByName(string $folderId, string $fileName): ?string
    {
        try {
            $this->initializeClient();

            $query = sprintf(
                "'%s' in parents and name = '%s' and trashed = false",
                $folderId,
                addslashes($fileName)
            );

            $response = $this->driveService->files->listFiles([
                'q' => $query,
                'fields' => 'files(id)',
                'pageSize' => 1
            ]);

            $files = $response->getFiles();
            return !empty($files) ? $files[0]->getId() : null;
        } catch (Exception $e) {
            Log::error('Google Drive get file by name failed: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Stream file content directly without temp file.
     */
    public function streamFileContent(string $fileId): ?string
    {
        try {
            $this->initializeClient();

            $response = $this->driveService->files->get($fileId, [
                'alt' => 'media'
            ]);

            return $response->getBody()->getContents();
        } catch (Exception $e) {
            Log::error('Google Drive stream content failed: ' . $e->getMessage());
            return null;
        }
    }
}
