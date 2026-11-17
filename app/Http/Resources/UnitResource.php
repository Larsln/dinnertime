<?php

namespace App\Http\Resources;

use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Unit
 */
class UnitResource extends JsonResource
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
            'code' => $this->displayCode(),
            'name' => $this->displayName(),
            'plural_name' => $this->displayPluralName(),
            'kind' => $this->kind,
            'factor_to_base' => $this->factor_to_base,
        ];
    }
}
