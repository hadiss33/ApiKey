<?php

namespace App\Services\ApiKey\Infrastructure;

use App\Services\ApiKey\Application\Contracts\RouteCatalog;
use App\Services\ApiKey\Domain\ApiKeyRules;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Cache;

/**
 * درخت prefix ها از روتر:
 *  ۱. فقط route های زیر مسیر(های) پایه (api_key.route_tree.base_paths)
 *  ۲. تا اولین پارامتر مثل {id} جلو می‌رود؛ route ای که بخش اولش پارامتر است گره نمی‌سازد (همیشه برای کلید بسته می‌ماند)
 *  ۳. مسیرهای فهرست «هرگز» حذف می‌شوند
 *  ۴. سطح هر route (RouteLevel) در شمارنده‌ی همه‌ی prefix های پدر تا عمق مجاز (api_key.route_tree.depth) حساب می‌شود
 * کش با امضای فهرست route ها کلید می‌خورد؛ با هر انتشار که route ها عوض شود خودبه‌خود تازه می‌شود.
 */
final class RouteTree implements RouteCatalog
{
    /** @var array<string, true>|null */
    private ?array $flat = null;

    public function __construct(private readonly Router $router) {}

    public function tree(): array
    {
        /** @var array<int, Route> $routes */
        $routes = $this->router->getRoutes()->getRoutes();

        $signature = md5(json_encode([
            array_map(fn(Route $r) => implode(',', $r->methods()) . ' ' . $r->uri() . ' ' . (string) $r->getAction(RouteLevel::ACTION_KEY), $routes),
            config('api_key.route_tree'),
            config('api_key.never'),
        ]));

        $ttl = max(60, (int) config('api_key.route_tree.cache_seconds', 3600));

        return Cache::remember('api_key:route_tree:' . $signature, $ttl, fn() => $this->build($routes));
    }

    public function has(string $prefix): bool
    {
        $this->flat ??= $this->collect($this->tree());

        return isset($this->flat[trim($prefix, '/')]);
    }

    /** @param  array<int, Route>  $routes */
    private function build(array $routes): array
    {
        $depth = max(1, (int) config('api_key.route_tree.depth', 3));
        $never = array_map([ApiKeyRules::class, 'segments'], (array) config('api_key.never', []));
        $bases = array_map([ApiKeyRules::class, 'segments'], (array) config('api_key.route_tree.base_paths', ['api/v2']));
        $nodes = [];

        foreach ($routes as $route) {
            $uri = ApiKeyRules::segments($route->uri());

            foreach ($never as $n) {
                if (ApiKeyRules::hasPrefix($uri, $n)) {
                    continue 2;
                }
            }

            $base = null;

            foreach ($bases as $candidate) {
                if (ApiKeyRules::hasPrefix($uri, $candidate)) {
                    $base = $candidate;
                    break;
                }
            }

            if ($base === null) {
                continue;
            }

            $static = [];

            foreach (array_slice($uri, count($base)) as $segment) {
                if (str_contains($segment, '{') || count($static) >= $depth) {
                    break;
                }

                $static[] = $segment;
            }

            if ($static === []) {
                continue; // بخش اول پارامتر است: گره‌ای ساخته نمی‌شود
            }

            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $level = RouteLevel::for($route, $method) === ApiKeyRules::LEVEL_WRITE ? 'write' : 'read';

                for ($i = 1; $i <= count($static); $i++) {
                    $prefix = implode('/', array_merge($base, array_slice($static, 0, $i)));
                    $nodes[$prefix] ??= ['prefix' => $prefix, 'segment' => $static[$i - 1], 'depth' => $i, 'read' => 0, 'write' => 0];
                    $nodes[$prefix][$level]++;
                }
            }
        }

        ksort($nodes);
        $byParent = [];

        foreach ($nodes as $node) {
            $parent = $node['depth'] === 1 ? '' : substr($node['prefix'], 0, (int) strrpos($node['prefix'], '/'));
            $byParent[$parent][] = $node;
        }

        $make = function (string $parent) use (&$make, &$byParent): array {
            return array_map(fn(array $n) => $n + ['children' => $make($n['prefix'])], $byParent[$parent] ?? []);
        };

        return $make('');
    }

    /** @return array<string, true> */
    private function collect(array $nodes): array
    {
        $out = [];

        foreach ($nodes as $node) {
            $out[$node['prefix']] = true;
            $out += $this->collect($node['children']);
        }

        return $out;
    }
}
