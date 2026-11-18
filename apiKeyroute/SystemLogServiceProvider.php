<?php

namespace App\Services\SystemLog\Infrastructure;

use App\Services\SystemLog\Application\Contracts\AccessStore;
use App\Services\SystemLog\Application\Contracts\AlertGateway;
use App\Services\SystemLog\Application\Contracts\AuditStore;
use App\Services\SystemLog\Application\Contracts\CategoryStore;
use App\Services\SystemLog\Application\Contracts\ErrorStore;
use App\Services\SystemLog\Application\Contracts\RequestContext;
use App\Services\SystemLog\Application\Contracts\TraceStore;
use App\Services\SystemLog\Application\Services\TraceService;
use App\Services\SystemLog\Domain\Access\AccessPolicy;
use App\Services\SystemLog\Infrastructure\Console\PruneErrors;
use App\Services\SystemLog\Infrastructure\Console\PruneTraces;
use App\Services\SystemLog\Infrastructure\Http\LaravelRequestContext;
use App\Services\SystemLog\Infrastructure\Notifications\ChannelAlertGateway;
use App\Services\SystemLog\Infrastructure\Persistence\Stores\EloquentAccessStore;
use App\Services\SystemLog\Infrastructure\Persistence\Stores\EloquentAuditStore;
use App\Services\SystemLog\Infrastructure\Persistence\Stores\EloquentCategoryStore;
use App\Services\SystemLog\Infrastructure\Persistence\Stores\EloquentErrorStore;
use App\Services\SystemLog\Infrastructure\Persistence\Stores\EloquentTraceStore;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\CachesConfiguration;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * ریشه‌ی ترکیب (composition root) سرویس SystemLog: اتصال پورت‌ها به پیاده‌سازی‌ها، config و lang داخل ماژول،
 * دستور و زمان‌بندی، روت‌های پنل، و رویدادهای صف برای trace_id هر جاب.
 */
class SystemLogServiceProvider extends ServiceProvider
{
    private const ROOT = __DIR__ . '/..';

    /**
     * exceptionهایی که لاراول پیش‌فرض «گزارش نمی‌کند» (۴۰۴، ۴۰۱، ۴۰۳، ...) ولی SystemLog آن‌ها را ثبت می‌کند
     * (bootstrap/app.php با stopIgnoring). عمداً نیست: ValidationException و HttpResponseException (ورودی نامعتبر / پاسخ ۴۲۲).
     * ثابت است (نه config) چون handler ممکن است قبل از ادغام config ماژول ساخته شود (Collision در CLI)؛
     * برای تغییر: error.record_ignored در config/system_log.php (آرایه‌ی کامل؛ [] = هیچ‌کدام).
     */
    public const RECORD_IGNORED = [
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Illuminate\Routing\Exceptions\BackedEnumCaseNotFoundException::class,
        \Symfony\Component\HttpKernel\Exception\HttpException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        \Illuminate\Database\MultipleRecordsFoundException::class,
        \Illuminate\Database\RecordNotFoundException::class,
        \Illuminate\Database\RecordsNotFoundException::class,
        \Symfony\Component\HttpFoundation\Exception\RequestExceptionInterface::class,
        \Illuminate\Session\TokenMismatchException::class,
    ];

    public function register(): void
    {
        // پیش‌فرض‌های ماژول زیر config/system_log.php پروژه (تنظیمات واقعی؛ بدون .env)
        $this->mergeDefaults(self::ROOT . '/config.php', 'system_log');

        // پورت‌ها ← پیاده‌سازی‌ها
        $this->app->singleton(RequestContext::class, LaravelRequestContext::class);
        $this->app->singleton(ErrorStore::class, EloquentErrorStore::class);
        $this->app->singleton(AuditStore::class, EloquentAuditStore::class);
        $this->app->singleton(AccessStore::class, EloquentAccessStore::class);
        $this->app->singleton(AlertGateway::class, ChannelAlertGateway::class);

        // TraceService singleton است (trace_id هر request/جاب در آن نگه‌داری می‌شود)
        $this->app->singleton(TraceStore::class, EloquentTraceStore::class);
        $this->app->singleton(TraceService::class);

        // دسته‌های خطا از دیتابیس (CategoryService، با کش و جایگزین config)؛ سیاست دسترسی از config
        $this->app->singleton(CategoryStore::class, EloquentCategoryStore::class);
        $this->app->singleton(AccessPolicy::class, fn () => new AccessPolicy((int) config('system_log.access.manager_branch_id', 1)));
    }

    /**
     * پیش‌فرض‌های ماژول را «زیر» config پروژه می‌گذارد. آرایه‌های انجمنی عمیق ادغام می‌شوند (فقط کلیدهای عوض‌شده را بنویس)
     * و آرایه‌های لیستی (redact_keys، categories، ...) اگر در config پروژه بیایند کامل جایگزین می‌شوند.
     * وقتی config کش شده، ادغام قبلاً در کش انجام شده است.
     */
    private function mergeDefaults(string $path, string $key): void
    {
        if ($this->app instanceof CachesConfiguration && $this->app->configurationIsCached()) {
            return;
        }

        $config = $this->app->make('config');
        $config->set($key, self::merge((array) require $path, (array) $config->get($key, [])));
    }

    /**
     * آیا امشب نوبت پاکسازی خطاهای حل‌شده است؟ روشن بودن + «هر N روز» با شماره‌ی روز از تقویم ثابت (به وقت تهران)،
     * پس فاصله‌ی دقیق N روز می‌ماند (نه «روز N هر ماه»). N=1 یعنی هر شب.
     */
    public static function pruneErrorsDue(?int $dayNumber = null): bool
    {
        if (! config('system_log.error.prune.enabled', false)) {
            return false;
        }

        $now = now('Asia/Tehran');
        $day = $dayNumber ?? intdiv($now->getTimestamp() + $now->utcOffset() * 60, 86400);

        return $day % max(1, (int) config('system_log.error.prune.every_days', 7)) === 0;
    }

    /** @param  array<string, mixed>  $base */
    private static function merge(array $base, array $override): array
    {
        foreach ($override as $name => $value) {
            $base[$name] = is_array($value) && is_array($base[$name] ?? null) && ! array_is_list($value)
                ? self::merge($base[$name], $value)
                : $value;
        }

        return $base;
    }

    public function boot(): void
    {
        // ترجمه‌ها داخل ماژول‌اند؛ کلیدها همان system_log.* می‌مانند
        $this->callAfterResolving('translator', fn ($translator) => $translator->getLoader()->addPath(self::ROOT . '/lang'));

        // مایگریشن‌ها عمداً خودکار نیستند (دستی با --path؛ Database/migrations)

        if ($this->app->runningInConsole()) {
            $this->commands([PruneTraces::class, PruneErrors::class]);
            // نمونه‌ی config برای پروژه: php artisan vendor:publish --tag=system-log-config
            $this->publishes([self::ROOT . '/config.php' => config_path('system_log.php')], 'system-log-config');
        }

        $this->callAfterResolving(Schedule::class, function (Schedule $schedule) {
            // پاکسازی شبانه‌ی ردپاها
            $schedule->command('system-log:prune-traces')
                ->dailyAt('04:00')
                ->timezone('Asia/Tehran')
                ->withoutOverlapping()
                ->onOneServer();

            // پاکسازی خطاهای حل‌شده: هر شب بیدار می‌شود ولی فقط اگر error.prune.enabled روشن باشد و نوبت «هر N روز» رسیده باشد اجرا می‌شود
            $schedule->command('system-log:prune-errors')
                ->dailyAt('04:30')
                ->timezone('Asia/Tehran')
                ->withoutOverlapping()
                ->onOneServer()
                ->when(fn () => self::pruneErrorsDue());
        });

        // API پنل؛ احراز هویت داخل روت‌ها (authWithJwt). کلید API (سرویس ApiKey) همین route‌ها را بدون تعریف دوباره صدا می‌زند
        Route::middleware('console_api')->prefix('api/v2/system-log')->group(self::ROOT . '/Presentation/routes.php');

        // ورکر صف طولانی‌عمر است؛ نام جاب در حال اجرا هم آدرس ردپا (trace_id جدا برای هر جاب) و هم محل خطا («job:Class») است.
        // عمداً بعد از شکست پاک نمی‌شود، چون لاراول خطای جاب را بعد از رویداد JobFailed گزارش می‌کند؛
        // با شروع جاب بعدی یا پایان موفق جایگزین/پاک می‌شود.
        Event::listen(JobProcessing::class, function (JobProcessing $e) {
            LaravelRequestContext::$currentJob = $e->job->resolveName();
            $this->app->make(TraceService::class)->startContext('job:' . $e->job->resolveName());
        });
        Event::listen(JobProcessed::class, function () {
            LaravelRequestContext::$currentJob = null;
            $this->app->make(TraceService::class)->endContext();
        });
        Event::listen([JobFailed::class, JobExceptionOccurred::class], fn () => $this->app->make(TraceService::class)->endContext());
    }
}
