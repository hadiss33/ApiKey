<?php

namespace App\Services\SystemLog\Infrastructure\Console;

use App\Services\SystemLog\Application\Services\TraceService;
use Illuminate\Console\Command;

/** پاکسازی ردپاهای قدیمی (فقط Trace؛ لاگ فعالیت و خطا دائمی‌اند). مدت نگه‌داری: system_log.trace.retention_days. */
class PruneTraces extends Command
{
    protected $signature = 'system-log:prune-traces';

    protected $description = 'Delete system_traces rows older than the retention period (traces only)';

    public function handle(TraceService $traces): int
    {
        $this->info('Deleted ' . $traces->prune() . ' trace rows.');

        return self::SUCCESS;
    }
}
