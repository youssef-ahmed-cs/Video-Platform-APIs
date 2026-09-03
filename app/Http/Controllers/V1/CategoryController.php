<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->query('per_page', 15);

        $paginator = Category::withCount('videos')
            ->with('videos:id,title')
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Categories retrieved successfully.',
            'data' => [
                'categories' => CategoryResource::collection($paginator->items()),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    public function show(Category $category)
    {
        $category->load([
            'videos' => fn($query) => $query->where('is_public', true)->orWhere('user_id', auth()->id()),
            'podcasts' => fn($query) => $query->where('is_public', true)->orWhere('user_id', auth()->id()),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category retrieved successfully.',
            'data' => [
                'category' => new CategoryResource($category),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Category::class);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $category = Category::create([
            'user_id' => auth()->id(),
            'name' => $validated['name'],
            'slug' => $this->generateUniqueSlug($validated['name']),
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully.',
            'data' => [
                'category' => new CategoryResource($category),
            ],
        ], 201);
    }

    public function update(Request $request, Category $category)
    {
        $this->authorize('update', $category);

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = $this->generateUniqueSlug($validated['name'], $category->id);
        }

        $category->fill($validated);
        $category->save();

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully.',
            'data' => [
                'category' => new CategoryResource($category),
            ],
        ]);
    }

    public function destroy(Category $category)
    {
        $this->authorize('delete', $category);

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category removed successfully.',
            'data' => [
                'category' => new CategoryResource($category),
            ],
        ]);
    }

    protected function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'category';
        $slug = $base;
        $counter = 1;

        while (Category::where('slug', $slug)
            ->when($ignoreId, fn($query) => $query->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public function search(Request $request)
    {
        $query = $request->input('query');

        if (!$query) {
            return response()->json([
                'success' => false,
                'message' => 'Query parameter is required.',
                'data' => null,
            ], 400);
        }

        $categories = Category::search($query)->get();

        return response()->json([
            'success' => true,
            'message' => 'Search results returned.',
            'data' => [
                'categories' => CategoryResource::collection($categories),
            ],
        ]);
    }
}
