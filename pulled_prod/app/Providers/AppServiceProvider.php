<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\URL;


class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // Register VideoStreaming module views
        $this->loadViewsFrom(base_path('Modules/VideoStreaming/Resources/views'), 'videostreaming');

        // Register Guide module views
        $this->loadViewsFrom(base_path('Modules/Guide/Resources/views'), 'guide');

        URL::forceScheme('https');

        // WhatsApp task notifications. Registered as observers rather than as calls inside
        // each controller so that every write path — web, ApiController, Api\TaskApiController
        // and the n8n integration — is covered by one piece of code.
        //
        // Registration itself issues no query, so there is no hasTable() guard here (that
        // would cost one extra query on every request). The observers themselves bail out
        // before touching the database: WhatsAppGate::suppressed() returns true for every
        // console context, which covers migrate, seeders and tinker.
        \App\Models\ProjectTask::observe(\App\Observers\ProjectTaskObserver::class);
        \App\Models\TaskComment::observe(\App\Observers\TaskCommentObserver::class);
    }
}
