<?php

namespace App\Observers;

use App\Models\Page;
use App\Models\PageRevision;

/**
 * Snapshot the previous blocks JSON every time a Page is saved, so the
 * admin can roll back. Caps history at 20 entries per page.
 */
class PageObserver
{
    public function updating(Page $page): void
    {
        // Only snapshot when blocks actually changed (or title).
        if (! $page->isDirty(['blocks', 'title'])) {
            return;
        }
        $previous = $page->getOriginal();
        // Guard: skip the very first save (no previous state worth keeping).
        if (! is_array($previous['blocks'] ?? null) && empty($previous['blocks'])) {
            return;
        }
        $blocks = $previous['blocks'];
        if (is_string($blocks)) {
            $blocks = json_decode($blocks, true) ?: [];
        }
        PageRevision::create([
            'page_id'    => $page->id,
            'user_id'    => optional(auth()->user())->id,
            'blocks'     => $blocks,
            'title'      => $previous['title'] ?? $page->title,
            'created_at' => now(),
        ]);

        // Prune to last 20 revisions.
        $extra = PageRevision::where('page_id', $page->id)
            ->orderByDesc('id')
            ->skip(20)
            ->take(100)
            ->pluck('id');
        if ($extra->isNotEmpty()) {
            PageRevision::whereIn('id', $extra)->delete();
        }
    }
}
