<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Pattern;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PatternController extends Controller
{
    public function index(): View
    {
        return view('admin.patterns.index', [
            'patterns' => Pattern::with('creator')->latest()->get(),
        ]);
    }

    /**
     * AJAX save — called by the page editor's «Save as pattern» button.
     * Accepts a name + a JSON blocks payload (one or more block defs).
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'   => ['required', 'string', 'max:120'],
            'icon'   => ['nullable', 'string', 'max:8'],
            'blocks' => ['required', 'array', 'min:1'],
        ]);
        $pattern = Pattern::create([
            'name'       => $data['name'],
            'icon'       => $data['icon'] ?? '💾',
            'blocks'     => $data['blocks'],
            'created_by' => optional($request->user())->id,
        ]);

        return response()->json(['ok' => true, 'id' => $pattern->id, 'name' => $pattern->name]);
    }

    public function destroy(Pattern $pattern): RedirectResponse
    {
        $pattern->delete();
        return redirect()->route('admin.patterns.index')->with('success', 'الگو حذف شد.');
    }

    /**
     * Server-render a saved pattern's blocks as a string of block-card HTML
     * ready to insert into the page editor. Each card uses an `__I__`
     * placeholder index — the editor's reconstruct() step rewrites these to
     * the next available block index on form submit.
     */
    public function render(Pattern $pattern): JsonResponse
    {
        $html = '';
        foreach ($pattern->blocks as $block) {
            $type = $block['type'] ?? null;
            if (! $type || ! \App\Support\Blocks\BlockRegistry::exists($type)) continue;
            $html .= view('admin.pages._block', [
                'type' => $type,
                'data' => $block['data'] ?? [],
                'i'    => '__I__',
            ])->render();
        }
        return response()->json(['ok' => true, 'html' => $html]);
    }
}
