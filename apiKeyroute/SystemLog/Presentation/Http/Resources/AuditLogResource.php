<?php

namespace App\Services\SystemLog\Presentation\Http\Resources;

use App\Services\SystemLog\Application\DTOs\LogRecord;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property LogRecord $resource */
class AuditLogResource extends JsonResource
{
    public function toArray($request): array
    {
        $l = $this->resource;
        $title = app()->getLocale() === 'fa' ? ($l->titleFa ?: $l->titleEn) : ($l->titleEn ?: $l->titleFa);

        return [
            'id' => $l->id,
            'type' => ['code' => $l->type, 'title' => $title ?: $l->type, 'category' => $l->category],
            'subject_id' => $l->subjectId,
            'branch_id' => $l->branchId,
            'operator' => $l->operatorId === null ? null : [
                'id' => $l->operatorId,
                'name' => trim($l->operatorFirstName . ' ' . $l->operatorLastName),
                'personnel_id' => $l->operatorPersonnelId,
            ],
            'ip' => $l->ip,
            'user_agent' => $l->userAgent,
            'details' => $l->details,
            'created_at' => $l->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
