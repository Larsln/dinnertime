<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\StoreResource;
use App\Models\Store;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class StoreController extends Controller
{
    /**
     * List the stores of the household.
     */
    public function index(): AnonymousResourceCollection
    {
        return StoreResource::collection(Store::query()->withCount('offers')->orderBy('name')->get());
    }

    /**
     * Add a store.
     */
    public function store(Request $request): StoreResource
    {
        $store = Store::create($this->validated($request, null));

        return new StoreResource($store->refresh()->loadCount('offers'));
    }

    /**
     * Rename a store or state whether the household currently buys there.
     */
    public function update(Request $request, Store $store): StoreResource
    {
        $store->update($this->validated($request, $store));

        return new StoreResource($store->loadCount('offers'));
    }

    /**
     * Delete a store together with what was noted as sold there.
     */
    public function destroy(Store $store): Response
    {
        $store->offers->each->delete();
        $store->delete();

        return response()->noContent();
    }

    /**
     * Validate a store. The name is required for a new store and must not
     * differ from another one only in case.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Store $store): array
    {
        $request->merge(array_filter(['name' => is_string($request->input('name')) ? trim($request->input('name')) : null]));

        return $request->validate([
            'name' => [
                $store === null ? 'required' : 'sometimes',
                'string',
                'max:100',
                function (string $attribute, string $value, Closure $fail) use ($store): void {
                    $taken = Store::query()
                        ->whereRaw('lower(name) = ?', [mb_strtolower($value)])
                        ->when($store, fn (Builder $query) => $query->whereKeyNot($store->id))
                        ->exists();

                    if ($taken) {
                        $fail(__('validation.unique', ['attribute' => $attribute]));
                    }
                },
            ],
            'is_active' => ['sometimes', 'boolean'],
            'is_preferred' => ['sometimes', 'boolean'],
        ]);
    }
}
