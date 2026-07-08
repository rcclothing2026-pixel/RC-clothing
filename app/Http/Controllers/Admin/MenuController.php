<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Collection;
use App\Models\Media;
use App\Models\MenuItem;
use App\Models\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function index(): View
    {
        $all = MenuItem::orderBy('position')->orderBy('id')->get();

        return view('admin.menus.index', [
            'locations' => MenuItem::LOCATIONS,
            'items' => $all->whereNull('parent_id')->groupBy('location'),
            'childrenByParent' => $all->whereNotNull('parent_id')->groupBy('parent_id'),
            'parentsByLocation' => $all->whereNull('parent_id')->groupBy('location'),
            'catalogCategories' => Category::where('is_active', true)->orderBy('position')->orderBy('name')->get(['id', 'name', 'slug', 'image_path']),
            'catalogCollections' => Collection::where('is_active', true)->orderBy('position')->orderBy('name')->get(['id', 'name', 'slug', 'image_path']),
        ]);
    }

    /**
     * Bulk-create menu items from selected categories / collections. For each
     * pick we auto-build a brick/masonry page-builder Page (editable later like
     * any page) that lists that category/collection's items, and point the menu
     * item at it. The item carries the source image so the mega-menu preview
     * works out of the box.
     */
    public function fromCatalog(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'location' => ['required', 'in:'.implode(',', array_keys(MenuItem::LOCATIONS))],
            'parent_id' => ['nullable', 'integer', 'exists:menu_items,id'],
            'category_ids' => ['nullable', 'array'],
            'category_ids.*' => ['integer'],
            'collection_ids' => ['nullable', 'array'],
            'collection_ids.*' => ['integer'],
            'build_page' => ['nullable', 'boolean'],
        ]);

        // A parent must be in the same location and itself top-level (one level).
        $parentId = $data['parent_id'] ?? null;
        if ($parentId) {
            $parent = MenuItem::find($parentId);
            if (! $parent || $parent->location !== $data['location'] || $parent->parent_id) {
                $parentId = null;
            }
        }

        $buildPage = $request->boolean('build_page');
        $position = (int) MenuItem::where('location', $data['location'])
            ->where('parent_id', $parentId)->max('position');

        $sources = [
            ['type' => 'category',   'filter' => 'category',   'models' => Category::whereIn('id', $data['category_ids'] ?? [])->get()],
            ['type' => 'collection', 'filter' => 'collection', 'models' => Collection::whereIn('id', $data['collection_ids'] ?? [])->get()],
        ];

        $count = 0;
        foreach ($sources as $src) {
            foreach ($src['models'] as $model) {
                $url = $buildPage
                    ? '/page/'.Page::brickForCatalog($src['type'], $model->slug, $model->name)->slug
                    : '/shop?'.$src['filter'].'='.$model->slug;

                MenuItem::create([
                    'location' => $data['location'],
                    'parent_id' => $parentId,
                    'label' => $model->name,
                    'url' => $url,
                    'image' => $model->image_path,
                    'position' => ++$position,
                    'is_active' => true,
                ]);
                $count++;
            }
        }

        if ($count === 0) {
            return back()->with('error', 'حداقل یک دسته‌بندی یا مجموعه را انتخاب کنید.');
        }

        return back()->with('success', "{$count} آیتم منو ساخته شد.");
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'location' => ['required', 'in:'.implode(',', array_keys(MenuItem::LOCATIONS))],
            'parent_id' => ['nullable', 'integer', 'exists:menu_items,id'],
            'label' => ['required', 'string', 'max:80'],
            'url' => ['required', 'string', 'max:300'],
        ]);

        // A parent must be in the same location and itself top-level (one level).
        if (! empty($data['parent_id'])) {
            $parent = MenuItem::find($data['parent_id']);
            if (! $parent || $parent->location !== $data['location'] || $parent->parent_id) {
                $data['parent_id'] = null;
            }
        }

        $data['position'] = (int) MenuItem::where('location', $data['location'])
            ->where('parent_id', $data['parent_id'] ?? null)->max('position') + 1;

        MenuItem::create($data);

        return back()->with('success', 'لینک منو افزوده شد.');
    }

    public function update(Request $request, MenuItem $menu): RedirectResponse
    {
        $data = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'url' => ['required', 'string', 'max:300'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        // Optional mega-menu image (shown on hover). Upload replaces; the
        // remove checkbox clears it back to the label-only default.
        if ($request->boolean('remove_image')) {
            $data['image'] = null;
        } elseif ($request->hasFile('image')) {
            $request->validate(['image' => ['image', 'max:4096']]);
            $media = Media::createFromUploadedFile(
                $request->file('image'), 'menu', $menu->label, (int) $request->user()?->id
            );
            $data['image'] = $media->url;
        }

        $menu->update($data);

        return back()->with('success', 'لینک منو به‌روزرسانی شد.');
    }

    public function destroy(MenuItem $menu): RedirectResponse
    {
        $menu->delete();

        return back()->with('success', 'لینک منو حذف شد.');
    }

    /** Swap position with the adjacent item in the same location. */
    public function move(Request $request, MenuItem $menu): RedirectResponse
    {
        $dir = $request->string('dir')->toString() === 'up' ? 'up' : 'down';
        $neighbour = MenuItem::where('location', $menu->location)
            ->where('parent_id', $menu->parent_id)
            ->when($dir === 'up',
                fn ($q) => $q->where('position', '<', $menu->position)->orderByDesc('position'),
                fn ($q) => $q->where('position', '>', $menu->position)->orderBy('position'))
            ->first();

        if ($neighbour) {
            [$menu->position, $neighbour->position] = [$neighbour->position, $menu->position];
            $menu->save();
            $neighbour->save();
        }

        return back();
    }
}
