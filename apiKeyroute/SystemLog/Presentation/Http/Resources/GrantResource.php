<?php

namespace App\Services\SystemLog\Presentation\Http\Resources;

use App\Services\SystemLog\Domain\Access\Grant;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property Grant $resource */
class GrantResource extends JsonResource
{
    public function toArray($request): array
    {
        $g = $this->resource;

        return [
            'operator_id' => $g->operatorId,
            'is_manager' => $g->isManager,
            'is_active' => $g->isActive,
            'all_categories' => $g->all || $g->isManager,
            'category_ids' => $g->categoryIds,
            'channel' => $g->channel?->value,
        ];
    }
}
