<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\SizeGuide;
use HTMLPurifier;
use HTMLPurifier_Config;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SizeGuideController extends Controller
{
    public function index(): View
    {
        return view('admin.size-guides.index', [
            'guides' => SizeGuide::withCount('products')->latest()->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.size-guides.form', ['guide' => new SizeGuide(['is_active' => true])]);
    }

    public function store(Request $request): RedirectResponse
    {
        SizeGuide::create($this->validated($request));

        return redirect()->route('admin.size-guides.index')->with('success', 'راهنمای سایز ساخته شد.');
    }

    public function edit(SizeGuide $sizeGuide): View
    {
        return view('admin.size-guides.form', ['guide' => $sizeGuide]);
    }

    public function update(Request $request, SizeGuide $sizeGuide): RedirectResponse
    {
        $sizeGuide->update($this->validated($request, $sizeGuide));

        return redirect()->route('admin.size-guides.index')->with('success', 'راهنمای سایز به‌روزرسانی شد.');
    }

    public function destroy(SizeGuide $sizeGuide): RedirectResponse
    {
        // Products keep working — size_guide_id just becomes null on the FK side.
        $sizeGuide->products()->update(['size_guide_id' => null]);
        $sizeGuide->delete();

        return back()->with('success', 'راهنمای سایز حذف شد.');
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?SizeGuide $guide = null): array
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'content' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'max:8192'],
            'remove_image' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        // Image: a new upload wins, then explicit remove, else keep existing.
        $imagePath = $guide?->image_path;
        if ($request->hasFile('image')) {
            $imagePath = Media::createFromUploadedFile(
                $request->file('image'), 'size-guides', $v['name'], (int) $request->user()?->id
            )->url;
        } elseif ($request->boolean('remove_image')) {
            $imagePath = null;
        }

        return [
            'name' => $v['name'],
            'content' => $this->purify($v['content'] ?? ''),
            'image_path' => $imagePath,
            'is_active' => $request->boolean('is_active'),
        ];
    }

    /** Allow a safe subset of table/text HTML for the sizing table. */
    private function purify(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }
        $config = HTMLPurifier_Config::createDefault();
        $config->set('HTML.Allowed', 'p,br,b,strong,i,em,u,ul,ol,li,h3,h4,small,table,thead,tbody,tr,th[colspan|rowspan],td[colspan|rowspan],span,div');
        $config->set('Cache.SerializerPath', storage_path('app/htmlpurifier'));
        if (! is_dir(storage_path('app/htmlpurifier'))) {
            @mkdir(storage_path('app/htmlpurifier'), 0775, true);
        }

        return (new HTMLPurifier($config))->purify($html);
    }
}
