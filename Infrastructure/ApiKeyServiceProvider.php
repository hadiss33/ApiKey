<?php

namespace App\Services\ApiKey\Infrastructure;

use App\Services\ApiKey\Application\Contracts\ApiKeyStore;
use App\Services\ApiKey\Application\Contracts\CategoryStore;
use App\Services\ApiKey\Application\Contracts\OperatorDirectory;
use App\Services\ApiKey\Application\Contracts\PathSecurity;
use App\Services\ApiKey\Application\Contracts\RouteCatalog;
use App\Services\ApiKey\Application\Services\CategoryRegistry;
use App\Services\ApiKey\Domain\ApiKeyRules;
use Illuminate\Contracts\Foundation\CachesConfiguration;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * ریشه‌ی ترکیب سرویس ApiKey: config و lang داخل ماژول، پورت ← پیاده‌سازی، دستورها، روت‌های پنل مدیریت کلید،
 * macro ‌ی ->apiKeyLevel() برای route ها، و listener جایگزینی احراز هویت پنل برای درخواست‌های دارای کلید (ReplacePanelAuth).
 *
 * اتصال یک بخش به کلید: مدیر در پنل یک «دسته» با قانون‌های prefix + سطح می‌سازد (دیتابیس، به‌ازای شعبه).
 * route جدیدی لازم نیست؛ route های همان prefix خودکار در درخت پنل می‌آیند و با کلید هم کار می‌کنند.
 */
class ApiKeyServiceProvider extends ServiceProvider
{
    private const ROOT = __DIR__ . '/..';

    public function register(): void
    {
        $this->mergeDefaults(self::ROOT . '/config.php', 'api_key');

        $this->app->singleton(ApiKeyStore::class, EloquentApiKeyStore::class);
        $this->app->singleton(CategoryStore::class, EloquentCategoryStore::class);
        $this->app->singleton(RouteCatalog::class, RouteTree::class);
        $this->app->singleton(CategoryRegistry::class);
        $this->app->singleton(OperatorDirectory::class, EloquentOperatorDirectory::class);
        $this->app->singleton(PathSecurity::class, HelperPathSecurity::class);

        // Route::post(...)->apiKeyLevel('read'): route سطح لازمش را خودش اعلام می‌کند (POST هایی که فقط می‌خوانند).
        // در register() ثبت می‌شود تا قبل از بارگذاری فایل‌های route آماده باشد؛ در action ذخیره می‌شود پس با route:cache هم می‌ماند.
        RoutingRoute::macro('apiKeyLevel', function (string $level) {
            if (! isset(ApiKeyRules::LEVELS[$level])) {
                throw new \InvalidArgumentException("apiKeyLevel must be 'read' or 'write'.");
            }

            /** @var RoutingRoute $this */
            $this->action[RouteLevel::ACTION_KEY] = $level;

            return $this;
        });
    }

    public function boot(): void
    {
        // ترجمه‌ها داخل ماژول؛ کلیدها api_key.*
        $this->callAfterResolving('translator', fn($translator) => $translator->getLoader()->addPath(self::ROOT . '/lang'));

        // وقتی route پیدا شد و درخواست کلید ما دارد: آن چهار میدلور کنار می‌روند و ApiKeyAuth می‌نشیند
        Event::listen(RouteMatched::class, new ReplacePanelAuth);

        // مایگریشن‌ها عمداً خودکار نیستند: php artisan migrate --path=app/Services/ApiKey/Database/migrations

        if ($this->app->runningInConsole()) {
            $this->commands([ApiKeyCommand::class, ApiKeyRoutesCommand::class]);
            $this->publishes([self::ROOT . '/config.php' => config_path('api_key.php')], 'api-key-config');
        }

        // پنل مدیریت کلیدها و دسته‌ها (اپراتور ادمین شعبه)
        Route::middleware('console_api')->prefix('api/v2/api-keys')->group(self::ROOT . '/Presentation/routes.php');
    }

    /** پیش‌فرض‌های ماژول زیر config پروژه (همان قاعده‌ی SystemLog: انجمنی عمیق ادغام، لیستی جایگزین) */
    private function mergeDefaults(string $path, string $key): void
    {
        if ($this->app instanceof CachesConfiguration && $this->app->configurationIsCached()) {
            return;
        }

        $config = $this->app->make('config');
        $config->set($key, self::merge((array) require $path, (array) $config->get($key, [])));
    }

    private static function merge(array $base, array $override): array
    {
        foreach ($override as $name => $value) {
            $base[$name] = is_array($value) && is_array($base[$name] ?? null) && ! array_is_list($value)
                ? self::merge($base[$name], $value)
                : $value;
        }

        return $base;
    }
}
