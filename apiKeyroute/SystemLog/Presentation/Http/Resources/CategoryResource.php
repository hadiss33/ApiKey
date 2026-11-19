<?php

namespace App\Services\SystemLog\Presentation\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** یک دسته‌ی خطا (آرایه‌ی id/kind/key/title_fa/title_en/classes از ErrorTaxonomy) */
class CategoryResource extends JsonResource
{
    public function toArray($request): array
    {
        $c = (array) $this->resource;
        $c['title'] = app()->getLocale() === 'fa' ? ($c['title_fa'] ?: $c['title_en']) : ($c['title_en'] ?: $c['title_fa']);

        return $c;
    }
}
