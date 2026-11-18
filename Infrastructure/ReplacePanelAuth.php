<?php

namespace App\Services\ApiKey\Infrastructure;

use App\Http\Middleware\AuthGuest;
use App\Http\Middleware\AuthWithJWT;
use App\Http\Middleware\DomainAccess;
use App\Http\Middleware\IpTrust;
use Illuminate\Routing\Events\RouteMatched;

/**
 * وقتی درخواست «کلید API ما» دارد، برای همین route:
 *   - DomainAccess، IpTrust و AuthWithJWT اجرا نمی‌شوند (خود کلید همان کارها را در ApiKeyAuth می‌کند)
 *   - ApiKeyAuth جایشان (بعد از گروه console_api) می‌نشیند
 * این کار روی خود route در لحظه‌ی RouteMatched انجام می‌شود (قبل از ساخته‌شدن زنجیره‌ی میدلور)، پس
 * فایل آن سه میدلور و bootstrap/app.php دست نمی‌خورند و route دوباره تعریف نمی‌شود.
 *
 * ایمنی:
 *   - بدون هدر کلید (یا هدر با پیشوند غیر از کلید ما) هیچ‌چیز تغییر نمی‌کند: درخواست اپراتور دقیقاً مثل قبل است.
 *   - کنار گذاشتن آن سه فقط همراه گذاشتن ApiKeyAuth است که در هر خطا ۴xx می‌دهد؛ route بی‌احراز نمی‌ماند.
 *   - با api_key.replace_panel_auth=false (پیش‌فرض ماژول) کل این مسیر خاموش است.
 *   - route بدون دسته در config برای کلید ۴۰۳ است، پس فقط prefixهای تعریف‌شده در دسترس‌اند.
 */
final class ReplacePanelAuth
{
    public function __invoke(RouteMatched $event): void
    {
        if (! config('api_key.replace_panel_auth', false) || ApiKeyHeader::extract($event->request) === null) {
            return;
        }

        $route = $event->route;
        // AuthGuest هم کنار می‌رود: بدون آن route هدر Bearer را مستقیم به AuthWithJWT می‌داد و کلید را رد می‌کرد
        $route->withoutMiddleware([DomainAccess::class, IpTrust::class, AuthWithJWT::class, AuthGuest::class]);

        $action = $route->getAction();
        $middleware = array_values((array) ($action['middleware'] ?? []));
        $at = array_search('console_api', $middleware, true);

        // بعد از گروه console_api (قبل از میدلورهای سطح route مثل core که به operator نیاز دارند)
        array_splice($middleware, $at === false ? 0 : $at + 1, 0, [ApiKeyAuth::class]);
        $action['middleware'] = $middleware;
        $route->setAction($action);

        // Route::gatherMiddleware() نتیجه را کش می‌کند (computedMiddleware)؛ اگر قبل از این listener کسی آن را صدا زده
        // باشد، تغییر بالا دیده نمی‌شد و ApiKeyAuth بی‌صدا نصب نمی‌شد (و آن سه میدلور هم می‌ماندند). با null کردنش دوباره ساخته می‌شود.
        $route->computedMiddleware = null;
    }
}
