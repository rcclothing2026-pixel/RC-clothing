{{--
    Dual-view HTML editor: a visual (WYSIWYG) pane and an HTML source pane that
    stay in sync, with a full formatting toolbar and a بصری ⇄ کد HTML toggle.
    Self-contained — no external library/CDN (works behind Iran filtering). The
    <textarea> holds the HTML and is what gets submitted; sanitize it server-side
    where untrusted (e.g. product description via HTMLPurifier).

    Props:
      name        submitted field name (required)
      value       initial HTML
      rows        source textarea rows (default 14)
      placeholder visual-pane empty hint (optional)
      attr        extra raw attributes for the source textarea, e.g. the page
                  builder's data-bk="data.html" (optional)

    Usage: @include('admin.partials.html-editor', ['name' => 'description', 'value' => old('description', $product->description), 'rows' => 14])
--}}
@include('admin.partials.html-editor-assets')

<div data-rte class="overflow-hidden rounded-lg border border-brand-200">
    <div data-rte-toolbar class="flex flex-wrap items-center gap-0.5 border-b border-brand-200 bg-brand-50 p-1.5"></div>
    <div data-rte-visual contenteditable="true" dir="rtl" data-placeholder="{{ $placeholder ?? 'متن را اینجا بنویسید…' }}" class="px-3 py-2 text-sm text-brand-800 focus:outline-none"></div>
    <textarea name="{{ $name }}" {!! $attr ?? '' !!} data-rte-source dir="ltr" spellcheck="false" rows="{{ $rows ?? 14 }}" class="hidden w-full bg-brand-50/40 px-3 py-2 font-mono text-xs text-brand-700 focus:outline-none">{{ $value ?? '' }}</textarea>
</div>
