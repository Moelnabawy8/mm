<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\PublicRelations\Repositories\Contracts\PrEntityRepositoryInterface;
use Modules\PublicRelations\Repositories\Contracts\PrFieldRepositoryInterface;
use Modules\PublicRelations\Repositories\Contracts\PrInteractionLogRepositoryInterface;
use Modules\PublicRelations\Repositories\Contracts\PrMilestoneRepositoryInterface;
use Modules\PublicRelations\Repositories\Eloquent\PrEntityRepository;
use Modules\PublicRelations\Repositories\Eloquent\PrFieldRepository;
use Modules\PublicRelations\Repositories\Eloquent\PrInteractionLogRepository;
use Modules\PublicRelations\Repositories\Eloquent\PrMilestoneRepository;

class PublicRelationsServiceProvider extends ServiceProvider
{
    protected string $moduleName = 'PublicRelations';

    protected string $moduleNameLower = 'publicrelations';

    /** @var array<class-string, class-string> */
    private const BINDINGS = [
        PrEntityRepositoryInterface::class => PrEntityRepository::class,
        PrInteractionLogRepositoryInterface::class => PrInteractionLogRepository::class,
        PrMilestoneRepositoryInterface::class => PrMilestoneRepository::class,
        PrFieldRepositoryInterface::class => PrFieldRepository::class,
    ];

    public function register(): void
    {
        foreach (self::BINDINGS as $interface => $implementation) {
            $this->app->bind($interface, $implementation);
        }

        $this->app->register(RouteServiceProvider::class);
    }

    public function boot(): void
    {
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->moduleName, 'Database/Migrations'));
    }

    protected function registerConfig(): void
    {
        $this->publishes([
            module_path($this->moduleName, 'Config/config.php') => config_path($this->moduleNameLower . '.php'),
        ], 'config');

        $this->mergeConfigFrom(module_path($this->moduleName, 'Config/config.php'), $this->moduleNameLower);
    }

    protected function registerViews(): void
    {
        $viewPath = resource_path('views/modules/' . $this->moduleNameLower);
        $sourcePath = module_path($this->moduleName, 'Resources/views');

        $this->publishes([$sourcePath => $viewPath], [$this->moduleNameLower . '-module-views']);

        // Only include the publish-override path if it actually exists — an
        // unconditional path here breaks `artisan view:cache` (Symfony's Finder
        // throws on a missing directory) until someone runs vendor:publish.
        $this->loadViewsFrom(is_dir($viewPath) ? [$viewPath, $sourcePath] : [$sourcePath], $this->moduleNameLower);
    }
}
