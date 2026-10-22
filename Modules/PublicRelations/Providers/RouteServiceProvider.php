<?php

declare(strict_types=1);

namespace Modules\PublicRelations\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        parent::boot();
    }

    public function map(): void
    {
        $this->mapApiRoutes();
        $this->mapWebRoutes();
    }

    protected function mapApiRoutes(): void
    {
        Route::prefix('api')
            ->middleware('api')
            ->group(module_path('PublicRelations', '/Routes/api.php'));
    }

    /**
     * Web routes declare their own 'web'/'auth' middleware and 'pr' prefix
     * inline, so no attributes are added at this level.
     */
    protected function mapWebRoutes(): void
    {
        Route::group([], module_path('PublicRelations', '/Routes/web.php'));
    }
}
