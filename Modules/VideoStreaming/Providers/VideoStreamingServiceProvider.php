<?php

namespace Modules\VideoStreaming\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\VideoStreaming\Services\GoogleDriveService;
use Modules\VideoStreaming\Services\VideoProcessingService;
use Modules\VideoStreaming\Services\HLSEncryptionService;
use Modules\VideoStreaming\Services\SegmentCacheService;
use Modules\VideoStreaming\Services\VideoStreamingService;

class VideoStreamingServiceProvider extends ServiceProvider
{
    /**
     * @var string $moduleName
     */
    protected $moduleName = 'VideoStreaming';

    /**
     * @var string $moduleNameLower
     */
    protected $moduleNameLower = 'videostreaming';

    /**
     * Boot the application events.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(base_path('Modules/' . $this->moduleName . '/Database/Migrations'));
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->register(RouteServiceProvider::class);
        
        // Register Services as Singletons
        $this->app->singleton(GoogleDriveService::class, function ($app) {
            return new GoogleDriveService();
        });
        
        $this->app->singleton(VideoProcessingService::class, function ($app) {
            return new VideoProcessingService();
        });
        
        $this->app->singleton(HLSEncryptionService::class, function ($app) {
            return new HLSEncryptionService();
        });
        
        $this->app->singleton(SegmentCacheService::class, function ($app) {
            return new SegmentCacheService();
        });
        
        $this->app->singleton(VideoStreamingService::class, function ($app) {
            return new VideoStreamingService(
                $app->make(GoogleDriveService::class),
                $app->make(VideoProcessingService::class),
                $app->make(HLSEncryptionService::class),
                $app->make(SegmentCacheService::class)
            );
        });
    }

    /**
     * Register config.
     *
     * @return void
     */
    protected function registerConfig()
    {
        $this->publishes([
            base_path('Modules/' . $this->moduleName . '/Config/config.php') => config_path($this->moduleNameLower . '.php'),
        ], 'config');
        $this->mergeConfigFrom(
            base_path('Modules/' . $this->moduleName . '/Config/config.php'), $this->moduleNameLower
        );
    }

    /**
     * Register views.
     *
     * @return void
     */
    public function registerViews()
    {
        $viewPath = resource_path('views/modules/' . $this->moduleNameLower);

        $sourcePath = base_path('Modules/' . $this->moduleName . '/Resources/views');

        $this->publishes([
            $sourcePath => $viewPath
        ], ['views', $this->moduleNameLower . '-module-views']);

        $this->loadViewsFrom(array_merge($this->getPublishableViewPaths(), [$sourcePath]), $this->moduleNameLower);
    }

    /**
     * Register translations.
     *
     * @return void
     */
    public function registerTranslations()
    {
        $langPath = resource_path('lang/modules/' . $this->moduleNameLower);

        if (is_dir($langPath)) {
            $this->loadTranslationsFrom($langPath, $this->moduleNameLower);
            $this->loadJsonTranslationsFrom($langPath);
        } else {
            $this->loadTranslationsFrom(base_path('Modules/' . $this->moduleName . '/Resources/lang'), $this->moduleNameLower);
            $this->loadJsonTranslationsFrom(base_path('Modules/' . $this->moduleName . '/Resources/lang'));
        }
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [
            GoogleDriveService::class,
            VideoProcessingService::class,
            HLSEncryptionService::class,
            SegmentCacheService::class,
            VideoStreamingService::class,
        ];
    }

    private function getPublishableViewPaths(): array
    {
        $paths = [];
        foreach (\Config::get('view.paths') as $path) {
            if (is_dir($path . '/modules/' . $this->moduleNameLower)) {
                $paths[] = $path . '/modules/' . $this->moduleNameLower;
            }
        }
        return $paths;
    }
}
