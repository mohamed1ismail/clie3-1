<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DishResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $nameAr = trim((string) ($this->name_ar ?? ''));
        $nameEn = trim((string) ($this->name_en ?? ''));
        $descAr = trim((string) ($this->description_ar ?? ''));
        $descEn = trim((string) ($this->description_en ?? ''));

        return [
            'id'                => $this->id,
            'category_id'       => $this->category_id,
            'category_name'     => $this->category?->name,
            'name'              => $this->name,
            'name_ar'           => $nameAr,
            'name_en'           => $nameEn,
            'slug'              => $this->slug,
            'description'       => $this->description,
            'description_ar'    => $descAr,
            'description_en'   => $descEn,
            'price'             => (float) $this->price,
            'image_url'         => $this->image_url,
            'is_available'      => (bool) $this->is_available,
            'is_featured'       => (bool) $this->is_featured,
            'calories'          => $this->calories,
            'prep_time_minutes' => $this->prep_time_minutes,
            'ingredients'       => $this->ingredients ?? [],
            'sizes'            => $this->whenLoaded('sizes', fn () =>
                $this->sizes->map(fn ($s) => [
                    'id'         => $s->id,
                    'size_name'  => $s->size_name,
                    'price'      => (float) $s->price,
                    'is_default' => (bool) $s->is_default,
                ])->values()
            , []),
            'created_at'       => $this->created_at?->toIso8601String(),
        ];
    }
}
