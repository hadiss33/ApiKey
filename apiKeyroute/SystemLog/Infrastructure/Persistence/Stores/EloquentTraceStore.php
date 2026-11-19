<?php

namespace App\Services\SystemLog\Infrastructure\Persistence\Stores;

use App\Services\SystemLog\Application\Contracts\TraceStore;
use App\Services\SystemLog\Infrastructure\Persistence\Models\SystemTrace;
use DateTimeInterface;

final class EloquentTraceStore implements TraceStore
{
    public function store(array $row): void
    {
        SystemTrace::query()->create($row);
    }

    public function prune(DateTimeInterface $before): int
    {
        $total = 0;
        $chunk = 5000;

        do {
            $deleted = SystemTrace::query()->where('created_at', '<', $before->format('Y-m-d H:i:s'))->orderBy('id')->limit($chunk)->delete();
            $total += $deleted;
        } while ($deleted >= $chunk);

        return $total;
    }
}
