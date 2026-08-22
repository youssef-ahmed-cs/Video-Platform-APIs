<?php

namespace App\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::withCount('videos')
            ->with('videos:id,title')
            ->latest()
            ->get();

        return response()->json([
            'categories' => CategoryResource::collection($categories),
        ]);
    }

    public function show(Category $category)
    {
        $category->load(['videos' => fn($query) => $query->where('is_public', true)->orWhere('user_id', auth()->id())]);

        return response()->json([
            'category' => new CategoryResource($category),
        ]);
    }

    public function store(Request $request)
    {
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
            'message' => 'Category created successfully.',
            'category' => new CategoryResource($category),
        ], 201);
    }

    public function update(Request $request, Category $category)
    {
        if ($category->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

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
            'message' => 'Category updated successfully.',
            'category' => new CategoryResource($category),
        ]);
    }

    public function destroy(Category $category)
    {
        if ($category->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $category->delete();

        return response()->json([
            'message' => 'Category removed successfully.',
            'category' => new CategoryResource($category),
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
}
