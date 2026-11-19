<?php

namespace App\Services\ApiKey\Infrastructure;

use App\Services\ApiKey\Application\Contracts\RouteCatalog;
use App\Services\ApiKey\Domain\ApiKeyRules;
use Illuminate\Console\Command;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;

/**
 * درخت prefix ها (همان منطق پنل) و route های مشکوک (پیام‌ها انگلیسی؛ ابزار کنسول).
 *
 *   php artisan api-key:routes                         جدول prefix ← تعداد route خواندنی/نوشتنی
 *   php artisan api-key:routes --json                  خروجی JSON پایدار (برای ذخیره به‌عنوان baseline)
 *   php artisan api-key:routes --baseline=ci/api-key-routes.json   مقایسه با baseline؛ اختلاف ← exit 1 (برای CI)
 *   php artisan api-key:routes --flag                  route های غیرGET که اعلام سطح ندارند ولی شبیه «خواندن» اند
 *
 * فقط روتر را می‌خواند؛ به دیتابیس وصل نمی‌شود. در بازبینی PR: route تازه‌ای که زیر یک prefix باز بیاید
 * خودبه‌خود برای دسته‌های دارای آن prefix در دسترس کلید می‌شود و در diff این دستور دیده می‌شود.
 */
class ApiKeyRoutesCommand extends Command
{
    protected $signature = 'api-key:routes {--json : Stable JSON output} {--baseline= : Compare with a saved JSON file (exit 1 on difference)} {--flag : List undeclared non-GET routes that look read-only}';

    protected $description = 'Show the API key prefix tree (read/write route counts) and suspicious routes';

    /** آخرین بخش مسیر که معمولاً یعنی «فقط می‌خواند» */
    private const READ_HINT = '/(^|[-_])(list|search|get|show|view|status|check|lookup|summary|details?|index|download|preview|statement|ledger|report|balance|treeview|items)($|[-_])/i';

    public function handle(RouteCatalog $catalog, Router $router): int
    {
        if ($this->option('flag')) {
            return $this->flagged($router);
        }

        $flat = $this->flatten($catalog->tree());

        if ($this->option('baseline')) {
            return $this->compare($flat, (string) $this->option('baseline'));
        }

        if ($this->option('json')) {
            $this->line(json_encode($flat, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->table(['prefix', 'read', 'write'], array_map(fn(string $p, array $c) => [$p, $c['read'], $c['write']], array_keys($flat), $flat));
        $this->line(count($flat) . ' prefix(es).');

        return self::SUCCESS;
    }

    /** @return array<string, array{read:int, write:int}> */
    private function flatten(array $nodes): array
    {
        $out = [];

        foreach ($nodes as $node) {
            $out[$node['prefix']] = ['read' => $node['read'], 'write' => $node['write']];
            $out += $this->flatten($node['children']);
        }

        ksort($out);

        return $out;
    }

    private function compare(array $current, string $path): int
    {
        if (! is_file($path)) {
            $this->error("Baseline file not found: {$path}");

            return self::FAILURE;
        }

        $saved = (array) json_decode((string) file_get_contents($path), true);
        $diff = [];

        foreach (array_keys($saved + $current) as $prefix) {
            if (! isset($saved[$prefix])) {
                $diff[] = ['+ new', $prefix, '-', $this->counts($current[$prefix])];
            } elseif (! isset($current[$prefix])) {
                $diff[] = ['- removed', $prefix, $this->counts($saved[$prefix]), '-'];
            } elseif ($saved[$prefix] != $current[$prefix]) {
                $diff[] = ['~ changed', $prefix, $this->counts($saved[$prefix]), $this->counts($current[$prefix])];
            }
        }

        if ($diff === []) {
            $this->info('No difference from baseline.');

            return self::SUCCESS;
        }

        $this->table(['change', 'prefix', 'baseline (r/w)', 'now (r/w)'], $diff);
        $this->error('API key route tree changed. Review the diff, then refresh the baseline with --json.');

        return self::FAILURE;
    }

    private function counts(array $c): string
    {
        return $c['read'] . '/' . $c['write'];
    }

    private function flagged(Router $router): int
    {
        $never = array_map([ApiKeyRules::class, 'segments'], (array) config('api_key.never', []));
        $rows = [];

        /** @var Route $route */
        foreach ($router->getRoutes()->getRoutes() as $route) {
            $methods = array_values(array_diff($route->methods(), ['HEAD', 'GET', 'OPTIONS']));
            $segments = ApiKeyRules::segments($route->uri());

            if ($methods === [] || RouteLevel::isDeclared($route) || $segments === []) {
                continue;
            }

            foreach ($never as $n) {
                if (ApiKeyRules::hasPrefix($segments, $n)) {
                    continue 2;
                }
            }

            $last = end($segments);

            if (! str_contains($last, '{') && preg_match(self::READ_HINT, $last)) {
                $rows[] = [implode('|', $methods), $route->uri()];
            }
        }

        usort($rows, fn(array $a, array $b) => [$a[1], $a[0]] <=> [$b[1], $b[0]]);
        $this->table(['method', 'uri'], $rows);
        $this->line(count($rows) . ' route(s). If one only reads, add ->apiKeyLevel(\'read\') to it.');

        return self::SUCCESS;
    }
}
