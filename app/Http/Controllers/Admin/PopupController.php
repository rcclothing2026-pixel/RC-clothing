<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Popup;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PopupController extends Controller
{
    public function index(): View
    {
        return view('admin.popups.index', ['popups' => Popup::latest()->get()]);
    }

    public function create(): View
    {
        return view('admin.popups.edit', ['popup' => new Popup(['is_active' => true, 'trigger' => 'load', 'delay' => 3, 'frequency' => 'session', 'pages' => 'all'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        Popup::create($this->validated($request));

        return redirect()->route('admin.popups.index')->with('success', 'پاپ‌آپ ساخته شد.');
    }

    public function edit(Popup $popup): View
    {
        return view('admin.popups.edit', ['popup' => $popup]);
    }

    public function update(Request $request, Popup $popup): RedirectResponse
    {
        $popup->update($this->validated($request));

        return back()->with('success', 'پاپ‌آپ ذخیره شد.');
    }

    public function destroy(Popup $popup): RedirectResponse
    {
        $popup->delete();

        return redirect()->route('admin.popups.index')->with('success', 'پاپ‌آپ حذف شد.');
    }

    /** @return array<string,mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'html' => ['nullable', 'string', 'max:200000'],
            'css' => ['nullable', 'string', 'max:200000'],
            'trigger' => ['required', 'in:'.implode(',', array_keys(Popup::TRIGGERS))],
            'delay' => ['nullable', 'integer', 'min:0', 'max:120'],
            'frequency' => ['required', 'in:'.implode(',', array_keys(Popup::FREQUENCIES))],
            'pages' => ['required', 'in:'.implode(',', array_keys(Popup::PAGES))],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['delay'] = (int) ($data['delay'] ?? 3);
        $data['is_active'] = $request->boolean('is_active');

        // Recombine the optional CSS pane into the single `html` column, in a
        // marked <style> block the editor splits back out on next load.
        $css = trim((string) ($data['css'] ?? ''));
        $data['html'] = ($css !== '' ? "<style data-popup-css>\n{$css}\n</style>\n" : '').(string) ($data['html'] ?? '');
        unset($data['css']); // not a column

        return $data;
    }
}
