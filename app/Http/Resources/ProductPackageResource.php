<?php

namespace App\Http\Resources;

use App\Models\ProductPackage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductPackage
 */
class ProductPackageResource extends JsonResource
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
            'label' => $this->label,
            'amount_in_base' => $this->amount_in_base,
            'status' => $this->status,
            'source' => $this->source,
        ];
    }
}
