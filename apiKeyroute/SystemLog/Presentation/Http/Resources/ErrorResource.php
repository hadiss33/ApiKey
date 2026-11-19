<?php

namespace App\Services\SystemLog\Presentation\Http\Resources;

use App\Services\SystemLog\Application\Contracts\RequestContext;
use App\Services\SystemLog\Application\DTOs\ErrorRecord;
use App\Services\SystemLog\Application\Services\CategoryService;
use App\Services\SystemLog\Domain\Error\ErrorTaxonomy;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property ErrorRecord $resource */
class ErrorResource extends JsonResource
{
    /** یک بار در هر request (نه به‌ازای هر ردیف لیست) */
    private static ?ErrorTaxonomy $taxonomy = null;

    public function toArray($request): array
    {
        $e = $this->resource;
        $tax = self::$taxonomy ??= app(CategoryService::class)->taxonomy();
        $locale = app()->getLocale();
        $label = fn (int $id) => ['id' => $id, 'key' => $tax->key($id), 'title' => $tax->title($id, $locale)];

        return [
            'ref' => $e->ref,
            'branch_id' => $e->branchId,
            'source' => $label($e->source),
            'type' => $label($e->type),
            'level' => ['key' => $e->level->key(), 'label' => __('system_log.levels.' . $e->level->key())],
            'status' => ['key' => $e->status->key(), 'label' => __('system_log.statuses.' . $e->status->key())],
            'code' => $e->code,
            'message' => $e->message,
            'exception_class' => $e->exceptionClass,
            'file' => $e->file,
            'line' => $e->line,
            'location' => $e->location,
            'status_code' => $e->statusCode,
            'actor' => $e->actorId === null ? null : ['type' => array_search($e->actorType, RequestContext::ACTOR_TYPES, true) ?: null, 'id' => $e->actorId],
            'ip' => $e->ip,
            'occurrences' => $e->occurrences,
            'first_seen_at' => $e->firstSeenAt?->format('Y-m-d H:i:s'),
            'last_seen_at' => $e->lastSeenAt?->format('Y-m-d H:i:s'),
            'note' => $e->note,
            'resolved_by' => $e->resolvedBy,
            'resolved_at' => $e->resolvedAt?->format('Y-m-d H:i:s'),
            // فقط در جزئیات پر می‌شوند
            'stack' => $e->stack,
            'details' => $e->details,
        ];
    }
}
