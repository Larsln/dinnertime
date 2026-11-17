<?php

namespace App\Http\Resources;

use App\Models\StoreSection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StoreSection
 */
class StoreSectionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'key' => $this->key,
            'name' => $this->displayName(),
            'position' => $this->position,
        ];
    }
}
