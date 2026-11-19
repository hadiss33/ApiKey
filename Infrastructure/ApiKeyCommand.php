<?php

namespace App\Services\ApiKey\Infrastructure;

use App\Services\ApiKey\Application\DTOs\ApiKeyRecord;
use App\Services\ApiKey\Application\Services\ApiKeyService;
use App\Services\ApiKey\Application\Services\CategoryRegistry;
use App\Services\ApiKey\Domain\ApiKeyException;
use App\Services\ApiKey\Domain\ApiKeyRules;
use Illuminate\Console\Command;

/**
 * مدیریت کلید از خط فرمان (تست و break-glass). پیام‌ها انگلیسی (ابزار کنسول).
 *
 *   php artisan api-key list       --branch=1
 *   php artisan api-key categories --branch=1
 *   php artisan api-key create     --branch=1 --operator=5 --name="Shop server" --categories=3,4
 *                                  [--type=public --origins=https://a.ir] [--ips=1.2.3.4] [--days=30|--no-expiry] [--env=test]
 *   php artisan api-key revoke     --branch=1 --operator=5 --id=7
 */
class ApiKeyCommand extends Command
{
    protected $signature = 'api-key
        {action : list | create | revoke | categories}
        {--branch= : Branch id}
        {--operator= : Operator id recorded as creator/revoker (the key acts as this operator)}
        {--id= : Key id (revoke)}
        {--name= : Key name (create)}
        {--type=secret : secret | public}
        {--env=live : live | test}
        {--categories= : Comma-separated category ids of this branch (see the categories action)}
        {--ips= : Comma-separated IPs/CIDRs}
        {--origins= : Comma-separated origins (public keys)}
        {--days= : Expiry in days}
        {--no-expiry : Key never expires}';

    protected $description = 'Manage API keys (list, create, revoke, categories)';

    public function handle(ApiKeyService $keys, CategoryRegistry $categories): int
    {
        try {
            return match ($this->argument('action')) {
                'list' => $this->listKeys($keys),
                'create' => $this->create($keys),
                'revoke' => $this->revoke($keys),
                'categories' => $this->categories($categories),
                default => $this->failWith('Unknown action. Use: list | create | revoke | categories'),
            };
        } catch (ApiKeyException $e) {
            return $this->failWith(__($e->getMessage(), $e->replace));
        }
    }

    private function listKeys(ApiKeyService $keys): int
    {
        $rows = array_map(fn(ApiKeyRecord $k) => [
            $k->id,
            $k->name,
            ApiKeyRules::typeKey($k->type),
            $k->prefix . '…',
            $k->status(),
            implode(',', $k->categoryIds),
            $k->expiresAt?->format('Y-m-d H:i') ?? '-',
            $k->lastUsedAt?->format('Y-m-d H:i') ?? '-',
        ], $keys->list($this->requiredInt('branch')));

        $this->table(['id', 'name', 'type', 'prefix', 'status', 'categories', 'expires', 'last used'], $rows);

        return self::SUCCESS;
    }

    private function create(ApiKeyService $keys): int
    {
        $record = $keys->issue($this->requiredInt('branch'), $this->requiredInt('operator'), [
            'name' => (string) ($this->option('name') ?: 'CLI key'),
            'type' => (string) $this->option('type'),
            'environment' => (string) $this->option('env'),
            'categories' => array_map('intval', $this->csv('categories')),
            'allowed_ips' => $this->csv('ips'),
            'origins' => $this->csv('origins'),
            'expires_at' => $this->option('days') ? now()->addDays((int) $this->option('days'))->toDateTimeString() : null,
            'no_expiry' => (bool) $this->option('no-expiry'),
        ]);

        $this->info("Key #{$record->id} created. Copy it now; it is not shown again:");
        $this->line($record->plainKey);

        return self::SUCCESS;
    }

    private function revoke(ApiKeyService $keys): int
    {
        $record = $keys->revoke($this->requiredInt('branch'), $this->requiredInt('id'), $this->requiredInt('operator'));
        $this->info("Key #{$record->id} revoked.");

        return self::SUCCESS;
    }

    private function categories(CategoryRegistry $categories): int
    {
        $rows = array_map(fn(array $c) => [
            $c['id'],
            $c['name'],
            $c['is_active'] ? 'yes' : 'no',
            implode(' ', array_map(fn(array $r) => $r['prefix'] . ':' . $r['level'], $c['rules'])),
        ], $categories->listForBranch($this->requiredInt('branch')));

        $this->table(['id', 'name', 'active', 'rules (prefix:level)'], $rows);

        return self::SUCCESS;
    }

    private function requiredInt(string $option): int
    {
        $value = $this->option($option);

        if (! is_numeric($value) || (int) $value <= 0) {
            throw new \InvalidArgumentException("--{$option} is required and must be a positive number.");
        }

        return (int) $value;
    }

    /** @return array<int, string> */
    private function csv(string $option): array
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $this->option($option))), fn($v) => $v !== ''));
    }

    private function failWith(string $message): int
    {
        $this->error($message);

        return self::FAILURE;
    }
}
