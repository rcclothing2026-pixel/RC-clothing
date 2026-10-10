<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\MenuItem;
use Illuminate\Database\Seeder;

/**
 * Pre-populate the menu manager with everything the storefront currently shows,
 * so the admin starts from a full, editable menu (rather than the hard-coded
 * fallback). Header categories become sub-menus of their parent category.
 * Idempotent per location — a location is only seeded when it is empty.
 */
class MenuSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedHeader();
        $this->seedFooter('footer_1', [
            ['Shop', '/shop'],
            ['About', '/page/about'],
            ['Contact', '/contact'],
            ['FAQ', '/page/faq'],
        ]);
        $this->seedFooter('footer_2', [
            ['Size Guide', '/page/size-guide'],
            ['Shipping & Returns', '/page/shipping-returns'],
            ['Terms', '/page/terms'],
            ['Privacy', '/page/privacy'],
        ]);
    }

    private function seedHeader(): void
    {
        // Key the guard off the "Shop" link, not "any header row": content
        // migrations (gift, concept stores) may have inserted their own header
        // items before this seeder runs, and we still want the core Shop +
        // category nav seeded alongside them.
        if (MenuItem::where('location', 'header')->where('url', '/shop')->exists()) {
            return;
        }

        $pos = 0;
        MenuItem::create(['location' => 'header', 'label' => 'Shop', 'url' => '/shop', 'position' => $pos++]);

        $topLevel = Category::where('is_active', true)
            ->whereNull('parent_id')
            ->orderBy('position')->get();

        foreach ($topLevel as $category) {
            $parent = MenuItem::create([
                'location' => 'header',
                'label' => $category->name,
                'url' => '/shop?category='.$category->slug,
                'position' => $pos++,
            ]);

            $childPos = 0;
            foreach (Category::where('is_active', true)->where('parent_id', $category->id)->orderBy('position')->get() as $sub) {
                MenuItem::create([
                    'location' => 'header',
                    'parent_id' => $parent->id,
                    'label' => $sub->name,
                    'url' => '/shop?category='.$sub->slug,
                    'position' => $childPos++,
                ]);
            }
        }
    }

    /** @param array<int,array{0:string,1:string}> $links */
    private function seedFooter(string $location, array $links): void
    {
        if (MenuItem::where('location', $location)->exists()) {
            return;
        }

        foreach ($links as $i => [$label, $url]) {
            MenuItem::create(['location' => $location, 'label' => $label, 'url' => $url, 'position' => $i]);
        }
    }
}
