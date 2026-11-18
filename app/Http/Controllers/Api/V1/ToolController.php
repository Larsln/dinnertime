<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ToolResource;
use App\Models\Tool;
use App\Services\Catalog\ToolMerger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class ToolController extends Controller
{
    /**
     * List the tool catalog, optionally filtered by name.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $tools = Tool::query()
            ->withCount('recipes')
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query
                ->whereLike('name', "%{$search}%"))
            ->orderBy('name')
            ->get();

        return ToolResource::collection($tools);
    }

    /**
     * Add a tool to the catalog.
     */
    public function store(Request $request): ToolResource
    {
        $tool = Tool::create($this->validated($request, null));

        return new ToolResource($tool->refresh()->loadCount('recipes'));
    }

    /**
     * Rename a tool or state whether the household owns it.
     */
    public function update(Request $request, Tool $tool): ToolResource
    {
        $tool->update($this->validated($request, $tool));

        return new ToolResource($tool->loadCount('recipes'));
    }

    /**
     * Merge the tool into another one and delete it.
     */
    public function merge(Request $request, Tool $tool, ToolMerger $merger): ToolResource
    {
        $data = $request->validate([
            'into' => ['required', 'integer', 'exists:tools,id', Rule::notIn([$tool->id])],
        ]);

        $target = Tool::findOrFail($data['into']);
        $merger->merge($tool, $target);

        return new ToolResource($target->loadCount('recipes'));
    }

    /**
     * Delete a tool no recipe needs.
     */
    public function destroy(Tool $tool): Response
    {
        abort_if($tool->recipes()->exists(), 409, __('messages.tool_in_use'));

        $tool->delete();

        return response()->noContent();
    }

    /**
     * Validate a tool. The name is required for a new tool.
     *
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Tool $tool): array
    {
        return $request->validate([
            'name' => [$tool === null ? 'required' : 'sometimes', 'string', 'max:100', Rule::unique('tools', 'name')->ignore($tool)],
            'is_owned' => ['sometimes', 'boolean'],
        ]);
    }
}
