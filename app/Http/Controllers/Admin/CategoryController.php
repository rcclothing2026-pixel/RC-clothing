<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Media;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.categories.index', [
            'categories' => Category::withCount('products')->orderBy('position')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.categories.form', [
            'category' => new Category(['is_active' => true, 'position' => 0]),
            'parents' => Category::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $category = Category::create($data);
        $this->handleImage($request, $category);

        return redirect()->route('admin.categories.index')->with('success', 'دسته «'.$data['name'].'» ساخته شد.');
    }

    public function edit(Category $category): View
    {
        return view('admin.categories.form', [
            'category' => $category,
            'parents' => Category::where('id', '!=', $category->id)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $category->update($this->validated($request, $category));
        $this->handleImage($request, $category);

        return redirect()->route('admin.categories.index')->with('success', 'دسته «'.$category->name.'» به‌روزرسانی شد.');
    }

    /** Store an uploaded category image (or remove the current one). */
    private function handleImage(Request $request, Category $category): void
    {
        if ($request->boolean('remove_image')) {
            $category->update(['image_path' => null]);
        }
        if ($request->hasFile('image')) {
            $request->validate(['image' => ['image', 'max:4096']]);
            $media = Media::createFromUploadedFile(
                $request->file('image'), 'categories', $category->name, (int) $request->user()?->id
            );
            $category->update(['image_path' => $media->url]);
        }
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->exists()) {
            return back()->with('success', 'این دسته محصول دارد و حذف نشد — ابتدا محصول‌ها را جابه‌جا کنید.');
        }
        $category->children()->update(['parent_id' => null]);
        $category->delete();

        return back()->with('success', 'دسته حذف شد.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Category $category = null): array
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140'],
            'parent_id' => ['nullable', 'exists:categories,id'],
            'description' => ['nullable', 'string', 'max:500'],
            'position' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        return [
            'name' => $v['name'],
            'slug' => $this->uniqueSlug(($v['slug'] ?? '') ?: $v['name'], $category?->id),
            'parent_id' => $v['parent_id'] ?? null,
            'description' => $v['description'] ?? null,
            'position' => (int) ($v['position'] ?? 0),
            'is_active' => $request->boolean('is_active'),
        ];
    }

    private function uniqueSlug(string $source, ?int $ignoreId): string
    {
        $base = Str::slug($source) ?: 'category';
        $slug = $base;
        $i = 2;
        while (Category::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
