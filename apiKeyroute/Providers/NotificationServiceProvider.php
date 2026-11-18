<?php

namespace App\Providers;

use App\Services\Notification\Application\Services\BotSubscriptionService;
use App\Services\Notification\Application\Services\NotificationManager;
use App\Services\Notification\Infrastructure\ApplicationCredentialRepository;
use Illuminate\Support\ServiceProvider;

class NotificationServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/notification.php', 'notification');

        $this->app->singleton(ApplicationCredentialRepository::class);
        $this->app->singleton(NotificationManager::class);
        $this->app->singleton(BotSubscriptionService::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
