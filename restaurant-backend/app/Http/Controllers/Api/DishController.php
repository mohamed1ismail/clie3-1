<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDishRequest;
use App\Http\Requests\UpdateDishRequest;
use App\Http\Resources\DishResource;
use App\Models\Dish;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;

class DishController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Dish::query()->with(['category', 'sizes']);

        if ($request->has('category_id')) {
            $query->where('category_id', $request->input('category_id'));
        }

        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        if ($request->boolean('available_only', false) || !$request->user()) {
            $query->where('is_available', true);
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $dishes = $query->orderBy('name', 'asc')->get();

        return DishResource::collection($dishes);
    }

    public function show(Dish $dish): DishResource
    {
        $dish->load(['category', 'sizes']);
        return new DishResource($dish);
    }

    public function store(StoreDishRequest $request): JsonResponse
    {
        $data = $request->validated();
        $sizes = $data['sizes'] ?? null;
        unset($data['sizes']); // sizes are saved separately

        $nameValue = trim((string) ($data['name_ar'] ?? $data['name_en'] ?? $data['name'] ?? ''));
        $descriptionValue = trim((string) ($data['description_ar'] ?? $data['description_en'] ?? $data['description'] ?? ''));

        if ($nameValue !== '') {
            $data['name'] = $nameValue;
        }

        if ($descriptionValue !== '') {
            $data['description'] = $descriptionValue;
        }

        $data['slug'] = $data['slug'] ?? Str::slug($data['name'] ?: ($data['name_ar'] ?: ($data['name_en'] ?: 'dish')));

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('dishes', 'public');
            $data['image_path'] = $path;
        }

        $dish = Dish::create($data);

        if (!empty($sizes)) {
            $this->syncSizes($dish, $sizes);
        }

        $dish->load(['category', 'sizes']);

        return response()->json([
            'message' => 'Dish created successfully',
            'data'    => new DishResource($dish),
        ], 201);
    }

    public function update(UpdateDishRequest $request, Dish $dish): JsonResponse
    {
        $data = $request->validated();

        // 'sizes' key present (even if empty array) triggers a full sync/replace.
        // Omitting the key leaves existing sizes untouched.
        $syncSizes = array_key_exists('sizes', $data);
        $sizes = $data['sizes'] ?? null;
        unset($data['sizes']);

        $nameValue = trim((string) ($data['name_ar'] ?? $data['name_en'] ?? $data['name'] ?? $dish->name ?? ''));
        $descriptionValue = trim((string) ($data['description_ar'] ?? $data['description_en'] ?? $data['description'] ?? $dish->description ?? ''));

        if ($nameValue !== '') {
            $data['name'] = $nameValue;
        }

        if ($descriptionValue !== '') {
            $data['description'] = $descriptionValue;
        }

        if (isset($data['name']) && empty($data['slug'])) {
            $data['slug'] = Str::slug($data['name'] ?: ($data['name_ar'] ?: ($data['name_en'] ?: $dish->name ?: 'dish')));
        }

        if ($request->hasFile('image')) {
            $path = $request->file('image')->store('dishes', 'public');
            $data['image_path'] = $path;
        }

        $dish->update($data);

        if ($syncSizes) {
            $this->syncSizes($dish, $sizes ?? []);
        }

        $dish->load(['category', 'sizes']);

        return response()->json([
            'message' => 'Dish updated successfully',
            'data'    => new DishResource($dish),
        ]);
    }

    public function destroy(Dish $dish): JsonResponse
    {
        $dish->delete();

        return response()->json([
            'message' => 'Dish deleted successfully',
        ]);
    }

    // ---------------------------------------------------------------------------
    // Private Helpers
    // ---------------------------------------------------------------------------

    /**
     * Replace all sizes for a dish with the provided array.
     * Ensures exactly one size is marked as_default (first one if none specified).
     *
     * @param Dish  $dish
     * @param array $sizes  Array of ['size_name', 'price', 'is_default?'] maps
     */
    private function syncSizes(Dish $dish, array $sizes): void
    {
        $dish->sizes()->delete(); // wipe existing

        if (empty($sizes)) {
            return;
        }

        $hasDefault = collect($sizes)->contains(fn ($s) => !empty($s['is_default']));

        foreach ($sizes as $index => $size) {
            $dish->sizes()->create([
                'size_name'  => $size['size_name'],
                'price'      => $size['price'],
                // If no size explicitly marked as default, make the first one default
                'is_default' => !empty($size['is_default']) || (!$hasDefault && $index === 0),
            ]);
        }
    }
}
