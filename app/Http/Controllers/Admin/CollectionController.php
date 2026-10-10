<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Collection;
use App\Models\Media;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CollectionController extends Controller
{
    public function index(): View
    {
        return view('admin.collections.index', [
            'collections' => Collection::withCount('products')->orderBy('position')->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.collections.form', ['collection' => new Collection(['is_active' => true, 'position' => 0])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        Collection::create($data);

        return redirect()->route('admin.collections.index')->with('success', 'مجموعه «'.$data['name'].'» ساخته شد.');
    }

    public function edit(Collection $collection): View
    {
        return view('admin.collections.form', ['collection' => $collection]);
    }

    public function update(Request $request, Collection $collection): RedirectResponse
    {
        $collection->update($this->validated($request, $collection));

        return redirect()->route('admin.collections.index')->with('success', 'مجموعه «'.$collection->name.'» به‌روزرسانی شد.');
    }

    public function destroy(Collection $collection): RedirectResponse
    {
        $collection->delete(); // products.collection_id is nulled by FK constraint

        return back()->with('success', 'مجموعه حذف شد.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?Collection $collection = null): array
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140'],
            'description' => ['nullable', 'string', 'max:500'],
            'position' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'image' => ['nullable', 'image', 'max:4096'],
            'remove_image' => ['nullable', 'boolean'],
        ]);

        // Resolve the collection image: a new upload wins, then an explicit
        // remove, otherwise keep whatever is already stored.
        $imagePath = $collection?->image_path;
        if ($request->hasFile('image')) {
            $imagePath = Media::createFromUploadedFile(
                $request->file('image'), 'collections', $v['name'], (int) $request->user()?->id
            )->url;
        } elseif ($request->boolean('remove_image')) {
            $imagePath = null;
        }

        return [
            'name' => $v['name'],
            'slug' => $this->uniqueSlug(($v['slug'] ?? '') ?: $v['name'], $collection?->id),
            'description' => $v['description'] ?? null,
            'position' => (int) ($v['position'] ?? 0),
            'is_active' => $request->boolean('is_active'),
            'image_path' => $imagePath,
        ];
    }

    private function uniqueSlug(string $source, ?int $ignoreId): string
    {
        $base = Str::slug($source) ?: 'collection';
        $slug = $base;
        $i = 2;
        while (Collection::where('slug', $slug)->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
