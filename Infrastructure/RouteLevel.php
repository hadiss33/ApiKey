<?php

namespace App\Services\ApiKey\Infrastructure;

use App\Services\ApiKey\Domain\ApiKeyRules;
use Illuminate\Routing\Route;

/**
 * منبع واحد «سطح لازم یک route» (هم برای درخت پنل، هم برای میدلور ApiKeyAuth).
 *   - route می‌تواند با ->apiKeyLevel('read'|'write') سطح خودش را اعلام کند (مثلاً POST ‌هایی که فقط می‌خوانند)
 *   - اعلام نکرده باشد: GET/HEAD/OPTIONS = read، بقیه = write (محافظه‌کارانه)
 */
final class RouteLevel
{
    public const ACTION_KEY = 'api_key_level';

    public static function for(Route $route, string $method): int
    {
        $declared = $route->getAction(self::ACTION_KEY);

        if (is_string($declared) && isset(ApiKeyRules::LEVELS[$declared])) {
            return ApiKeyRules::LEVELS[$declared];
        }

        return ApiKeyRules::levelForMethod($method);
    }

    public static function isDeclared(Route $route): bool
    {
        return is_string($route->getAction(self::ACTION_KEY));
    }
}
