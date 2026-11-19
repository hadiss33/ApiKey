<?php

namespace App\Services\SystemLog\Presentation\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** نوع رویداد (آرایه‌ی id/code/category/title_fa/title_en از AuditStore::types) */
class TypeResource extends JsonResource
{
    public function toArray($request): array
    {
        return (array) $this->resource;
    }
}
