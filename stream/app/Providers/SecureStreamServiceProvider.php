<?php

namespace App\Providers;

use App\Services\HlsEncryptionService;
use App\Services\Storage\StorageManager;
use App\Services\StreamService;
use App\Services\VideoProcessingService;
use Illuminate\Support\ServiceProvider;

class SecureStreamServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(StorageManager::class, function () {
            return new StorageManager();
        });

        $this->app->singleton(StreamService::class, function () {
            return new StreamService();
        });

        $this->app->singleton(VideoProcessingService::class, function () {
            return new VideoProcessingService();
        });

        $this->app->singleton(HlsEncryptionService::class, function () {
            return new HlsEncryptionService();
        });
    }

    public function boot(): void
    {
        //
    }
}
