<?php

namespace App\Services\ApiKey\Infrastructure;

use App\Services\ApiKey\Application\Services\CategoryRegistry;
use App\Services\ApiKey\Domain\ApiKeyRules;
use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

/**
 * فهرست route‌ها و دسته/سطحی که کلید برای هرکدام لازم دارد (پیام‌ها انگلیسی؛ ابزار کنسول).
 *
 *   php artisan api-key:routes                  route‌های باز برای کلید (دسته‌دار)
 *   php artisan api-key:routes --category=system_log
 *   php artisan api-key:routes --unmapped       route‌های بدون دسته (برای کلید بسته‌اند)
 *
 * فقط روتر را می‌خواند؛ به دیتابیس وصل نمی‌شود. در بازبینی PR اجرا شود: route جدیدی که زیر یک prefix دسته‌دار بیاید
 * خودبه‌خود با همان دسته در دسترس کلید می‌شود.
 */
class ApiKeyRoutesCommand extends Command
{
    protected $signature = 'api-key:routes {--category= : Only this category key} {--unmapped : Show routes that have no category}';

    protected $description = 'List routes and the API key category/level each one needs';

    public function handle(Router $router, CategoryRegistry $categories): int
    {
        $rows = [];

        /** @var Route $route */
        foreach ($router->getRoutes()->getRoutes() as $route) {
            $category = $categories->resolve($route->uri());

            if ($this->option('unmapped') ? $category !== null : $category === null) {
                continue;
            }

            if ($this->option('category') && ($category['key'] ?? null) !== $this->option('category')) {
                continue;
            }

            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $level = ApiKeyRules::levelKey(ApiKeyRules::levelForMethod($method));
                $defined = $category !== null && isset($category['levels'][$level]);

                $rows[] = [
                    $method,
                    $route->uri(),
                    $category['key'] ?? '-',
                    $category === null ? '-' : $level . ($defined && $category['active'] ? '' : ' (closed)'),
                ];
            }
        }

        usort($rows, fn (array $a, array $b) => [$a[2], $a[1], $a[0]] <=> [$b[2], $b[1], $b[0]]);
        $this->table(['method', 'uri', 'category', 'level'], $rows);
        $this->line(count($rows) . ' route(s).');

        return self::SUCCESS;
    }
}
