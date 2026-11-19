<?php

namespace App\Services\SystemLog\Infrastructure\Console;

use App\Services\SystemLog\Application\Services\ErrorService;
use Illuminate\Console\Command;

/**
 * پاکسازی خطاهای «حل‌شده» از system_errors (new/investigating/ignored دست نمی‌خورند).
 * زمان‌بندی و روشن/خاموش بودن: config('system_log.error.prune') ← SystemLogServiceProvider.
 * دستی: php artisan system-log:prune-errors [--days=N] [--dry-run]   (--dry-run فقط می‌شمارد و چیزی حذف نمی‌کند)
 */
class PruneErrors extends Command
{
    protected $signature = 'system-log:prune-errors {--days= : only errors resolved at least N days ago (default: config error.prune.resolved_older_than_days)} {--dry-run : count only, delete nothing}';

    protected $description = 'Delete resolved errors from system_errors (only status=resolved)';

    public function handle(ErrorService $errors): int
    {
        $days = $this->option('days');

        if ($days !== null && (! ctype_digit((string) $days))) {
            $this->error('--days must be a non-negative integer');

            return self::FAILURE;
        }

        $dry = (bool) $this->option('dry-run');
        $count = $errors->pruneResolved($days === null ? null : (int) $days, $dry);

        $this->info(($dry ? 'Would delete ' : 'Deleted ') . $count . ' resolved error rows.');

        return self::SUCCESS;
    }
}
