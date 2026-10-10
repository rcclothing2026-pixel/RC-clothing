<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Page;
use App\Support\Blocks\BlockRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PageController extends Controller
{
    public function index(): View
    {
        return view('admin.pages.index', [
            'pages' => Page::orderByDesc('is_home')->orderBy('position')->orderBy('title')->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.pages.edit', [
            'page' => new Page(['blocks' => [], 'is_published' => true]),
            'registry' => BlockRegistry::all(),
            'templates' => Page::templates(),
            'patterns' => \App\Models\Pattern::latest()->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['blocks'] = $this->parseBlocks($request);
        $this->ensureSingleHome($data, null);

        $page = Page::create($data);

        return redirect()->route('admin.pages.edit', $page)->with('success', 'صفحه ساخته شد.');
    }

    public function edit(Page $page): View
    {
        return view('admin.pages.edit', [
            'page' => $page,
            'registry' => BlockRegistry::all(),
            'templates' => Page::templates(),
            'patterns' => \App\Models\Pattern::latest()->get(),
        ]);
    }

    public function update(Request $request, Page $page): RedirectResponse
    {
        $data = $this->validated($request, $page);
        $data['blocks'] = $this->parseBlocks($request);
        $this->ensureSingleHome($data, $page->id);

        $page->update($data);

        return back()->with('success', 'صفحه ذخیره شد.');
    }

    public function destroy(Page $page): RedirectResponse
    {
        // The home page is re-provisioned automatically and deleting it would
        // break the storefront — block it rather than let it 500 downstream.
        if ($page->is_home) {
            return redirect()->route('admin.pages.index')->with('error', 'صفحه اصلی قابل حذف نیست.');
        }

        $page->delete();

        return redirect()->route('admin.pages.index')->with('success', 'صفحه حذف شد.');
    }

    /**
     * Reset the home page back to the rastah-style editorial default
     * composition. One-click way for the admin to adopt the new layout
     * without us destructively overwriting a customized live home.
     */
    public function resetHome(): RedirectResponse
    {
        $home = Page::where('is_home', true)->first();
        if (! $home) {
            $home = Page::provisionHome();
        } else {
            $home->forceFill(['blocks' => Page::defaultHomeBlocks()])->save();
        }

        return redirect()->route('admin.pages.edit', $home)
            ->with('success', 'چیدمان خانه به حالت پیش‌فرض ادیتوریال بازگردانده شد. تصاویر هیرو را در ویرایشگر بارگذاری کنید.');
    }

    /** Revision history list view. */
    public function revisions(Page $page): View
    {
        return view('admin.pages.revisions', [
            'page' => $page,
            'revisions' => $page->revisions()->with('user')->orderByDesc('id')->take(20)->get(),
        ]);
    }

    /** Restore a specific revision's blocks onto the page. */
    public function restoreRevision(Page $page, \App\Models\PageRevision $revision): RedirectResponse
    {
        if ($revision->page_id !== $page->id) {
            abort(404);
        }
        $page->forceFill(['blocks' => $revision->blocks])->save();

        return redirect()->route('admin.pages.edit', $page)
            ->with('success', 'نسخهٔ پیشین بازگردانده شد.');
    }

    /**
     * Apply a pre-composed template to a page. mode=replace overwrites the
     * page's blocks; mode=append adds the template's blocks at the end.
     * The admin always confirms via JS prompt before submission.
     */
    public function applyTemplate(Request $request, Page $page): RedirectResponse
    {
        $data = $request->validate([
            'template' => ['required', 'string'],
            'mode'     => ['nullable', 'in:replace,append'],
        ]);
        $templates = Page::templates();
        if (! isset($templates[$data['template']])) {
            return back()->with('error', 'قالب موردنظر یافت نشد.');
        }
        $tpl = $templates[$data['template']];
        $mode = $data['mode'] ?? 'replace';
        $page->forceFill([
            'blocks' => $mode === 'append'
                ? array_merge($page->blocks ?? [], $tpl['blocks'])
                : $tpl['blocks'],
        ])->save();

        return redirect()->route('admin.pages.edit', $page)
            ->with('success', 'قالب «'.$tpl['label'].'» اعمال شد.');
    }

    /**
     * Live-preview render. Takes the in-progress (unsaved) block payload from
     * the editor and renders the storefront `page` view against a TRANSIENT
     * Page model — nothing is persisted. The editor swaps this HTML into the
     * preview iframe (srcdoc) on every debounced field change so the admin
     * sees changes without hitting Save.
     */
    public function preview(Request $request): \Illuminate\Http\Response
    {
        $page = new Page([
            'title'           => (string) $request->input('title', 'پیش‌نمایش'),
            'slug'            => (string) $request->input('slug', 'preview'),
            'seo_title'       => (string) $request->input('seo_title', ''),
            'seo_description' => (string) $request->input('seo_description', ''),
            'blocks'          => $this->parseBlocks($request),
            'bg_color'        => (string) $request->input('bg_color', ''),
            'bg_image'        => (string) $request->input('bg_image', ''),
            'is_published'    => true,
            'is_home'         => $request->boolean('is_home'),
        ]);

        return response(view('page', ['page' => $page])->render());
    }

    /**
     * Render a SINGLE block for the live editor — tiny, fast payload the editor
     * swaps in place (by data-bid) instead of re-rendering the whole page, so
     * edits reflect near-instantly even on a slow link.
     */
    public function previewBlock(Request $request): \Illuminate\Http\Response
    {
        $type = (string) $request->input('type');
        if (! BlockRegistry::exists($type)) {
            return response('', 200);
        }
        $bid = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $request->input('_bid', ''));
        $block = [
            'type' => $type,
            'data' => BlockRegistry::sanitize($type, (array) $request->input('data', [])),
            '_v'   => 'all', // always render in the editor preview, ignore visibility
            '_bid' => $bid !== '' ? substr($bid, 0, 32) : 'preview',
        ];

        return response(view('blocks.render', ['block' => $block])->render());
    }

    /** Image upload for image/hero blocks; returns the public URL. */
    public function upload(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'image', 'max:4096']]);
        $media = Media::createFromUploadedFile(
            $request->file('file'), 'pages', null, (int) $request->user()?->id
        );

        return response()->json(['url' => $media->url]);
    }

    /** @return array<string,mixed> */
    private function validated(Request $request, ?Page $page = null): array
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'slug' => ['nullable', 'string', 'max:160', 'alpha_dash'],
            'is_published' => ['nullable', 'boolean'],
            'is_home' => ['nullable', 'boolean'],
            'show_in_nav' => ['nullable', 'boolean'],
            'seo_title' => ['nullable', 'string', 'max:160'],
            'seo_description' => ['nullable', 'string', 'max:300'],
            'bg_color' => ['nullable', 'string', 'max:16'],
            'bg_image' => ['nullable', 'string', 'max:2048'],
        ]);

        $data['is_published'] = $request->boolean('is_published');
        $data['is_home'] = $request->boolean('is_home');
        $data['show_in_nav'] = $request->boolean('show_in_nav');

        $slug = $data['slug'] ?? null;
        if (! $slug) {
            $slug = Str::slug($data['title']) ?: ('page-'.Str::random(6));
        }
        // Keep slug unique.
        $base = $slug;
        $i = 1;
        while (Page::where('slug', $slug)->when($page, fn ($q) => $q->whereKeyNot($page->id))->exists()) {
            $slug = $base.'-'.(++$i);
        }
        $data['slug'] = $slug;

        return $data;
    }

    /** Only one page may be the homepage. */
    private function ensureSingleHome(array $data, ?int $exceptId): void
    {
        if (! empty($data['is_home'])) {
            Page::where('is_home', true)
                ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
                ->update(['is_home' => false]);
        }
    }

    /** @return array<int,array{type:string,data:array,_v:string}> */
    private function parseBlocks(Request $request): array
    {
        $blocks = [];
        $visAllowed = ['all', 'desktop', 'mobile', 'auth', 'guest'];
        foreach ((array) $request->input('blocks', []) as $raw) {
            $type = is_array($raw) ? ($raw['type'] ?? null) : null;
            if (! $type || ! BlockRegistry::exists($type)) {
                continue;
            }
            $v = (string) ($raw['_v'] ?? 'all');
            $data = BlockRegistry::sanitize($type, (array) ($raw['data'] ?? []));
            $bid = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($raw['_bid'] ?? ''));
            $blocks[] = [
                'type' => $type,
                'data' => $data,
                '_v'   => in_array($v, $visAllowed, true) ? $v : 'all',
                '_bid' => $bid !== '' ? substr($bid, 0, 32) : ('b'.substr(md5($type.json_encode($data).count($blocks)), 0, 10)),
            ];
        }

        return $blocks;
    }
}
