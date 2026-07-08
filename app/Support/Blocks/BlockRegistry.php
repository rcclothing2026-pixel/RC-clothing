<?php

namespace App\Support\Blocks;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Single source of truth for page-builder blocks. Every block type declares a
 * Persian label, an icon, and a field schema. The same definitions drive the
 * admin editor (which inputs to show), server-side validation (which keys are
 * allowed), and the default data used when a block is added.
 *
 * Field types: text | textarea | richtext | number | image | select | lines
 * | picker | multipicker (lines = one value per line, stored as an array;
 * picker = single search-select; multipicker = search + multi-select chips
 * stored as a comma-joined slug string).
 */
class BlockRegistry
{
    /** @var HTMLPurifier|null */
    private static $purifier = null;

    /** @return array<string, array{label:string, icon:string, fields:array}> */
    public static function all(): array
    {
        return [
            'hero_banner' => [
                'label' => 'Store Hero Banner',
                'icon' => '🛒',
                'fields' => [], // edited in the dedicated Hero admin (admin → بنر هیرو)
            ],
            'motion_banner' => [
                'label' => 'Motion Banner (Slideshow)',
                'icon' => '🎞',
                'fields' => [
                    ['key' => 'interval', 'label' => 'Change Interval (seconds)', 'type' => 'number'],
                    ['key' => 'slides', 'label' => 'Slides', 'type' => 'repeater', 'sub' => [
                        ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
                        ['key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'text'],
                        ['key' => 'image', 'label' => 'Product Image', 'type' => 'image'],
                        ['key' => 'color', 'label' => 'Blob Color', 'type' => 'select', 'options' => [
                            'purple' => 'Purple', 'amber' => 'Amber', 'teal' => 'Teal', 'red' => 'Club Clay', 'dark' => 'Dark',
                        ]],
                        ['key' => 'cta_text', 'label' => 'Button Text', 'type' => 'text'],
                        ['key' => 'cta_link', 'label' => 'Button Link', 'type' => 'text'],
                    ]],
                ],
            ],
            'hero' => [
                'label' => 'Hero (Large Header)',
                'icon' => '🖼',
                'fields' => [
                    ['key' => 'badge', 'label' => 'Small Label', 'type' => 'text'],
                    ['key' => 'title', 'label' => 'Title', 'type' => 'text'],
                    ['key' => 'accent', 'label' => 'Accent Part of Title', 'type' => 'text'],
                    ['key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'textarea'],
                    ['key' => 'cta_text', 'label' => 'Button Text', 'type' => 'text'],
                    ['key' => 'cta_link', 'label' => 'Button Link', 'type' => 'text'],
                    ['key' => 'image', 'label' => 'Image', 'type' => 'image'],
                    ['key' => 'style', 'label' => 'Background', 'type' => 'select', 'options' => [
                        'light' => 'Light', 'grad-red-light' => 'Clay Gradient', 'grad-dark-red' => 'Dark Clay Gradient', 'dark' => 'Dark',
                    ]],
                ],
            ],
            'product_grid' => [
                'label' => 'Product Grid',
                'icon' => '🛍',
                'fields' => [
                    // Content
                    ['key' => 'heading', 'label' => 'Section Title', 'type' => 'text'],
                    ['key' => 'products', 'label' => 'Selected Products (search and choose; empty = use the source below)', 'type' => 'multipicker', 'source' => 'products'],
                    ['key' => 'feed', 'label' => 'Product Source (when no products are selected)', 'type' => 'picker', 'source' => 'product_feed'],
                    ['key' => 'limit', 'label' => 'Count', 'type' => 'number'],
                    ['key' => 'shop_all_link', 'label' => '"View All" Link (empty = shop)', 'type' => 'text'],

                    // Layout mode
                    ['key' => 'layout', 'label' => 'Layout', 'type' => 'select', 'options' => [
                        'grid'     => 'Grid (fixed columns)',
                        'carousel' => 'Horizontal Slider (editorial style)',
                    ]],

                    // Grid-mode column counts (per breakpoint)
                    ['key' => 'columns_mobile', 'label' => 'Columns — Mobile (grid)', 'type' => 'select', 'options' => [
                        '1' => '1 Column', '2' => '2 Columns', '3' => '3 Columns',
                    ]],
                    ['key' => 'columns_desktop', 'label' => 'Columns — Desktop (grid)', 'type' => 'select', 'options' => [
                        '2' => '2 Columns', '3' => '3 Columns', '4' => '4 Columns', '5' => '5 Columns', '6' => '6 Columns',
                    ]],

                    // Carousel-mode card size. Fullscreen was removed —
                    // it took over the entire viewport and broke editorial flow.
                    ['key' => 'card_size', 'label' => 'Card Size (slider)', 'type' => 'select', 'options' => [
                        'compact' => 'Compact — 4–5 cards visible',
                        'med'     => 'Medium — 3 cards desktop, 2 mobile',
                        'large'   => 'Large — 2 cards desktop, 1.2 mobile',
                    ]],

                    // Card appearance
                    ['key' => 'card_style', 'label' => 'Product Card Style', 'type' => 'select', 'options' => [
                        'minimal'  => 'Minimal (photo + name + price + add button)',
                        'detailed' => 'Detailed (with hover, badge, buttons)',
                    ]],
                    ['key' => 'card_aspect', 'label' => 'Card Image Ratio', 'type' => 'select', 'options' => [
                        'portrait' => 'Portrait 3:4 (default)',
                        'tall'     => 'Tall 2:3',
                        'square'   => 'Square 1:1',
                        'wide'     => 'Landscape 4:3',
                    ]],

                    // Section spacing
                    ['key' => 'container', 'label' => 'Section Width', 'type' => 'select', 'options' => [
                        'default' => 'Default (max-w-7xl)',
                        'wide'    => 'Wide (max-w-screen-2xl)',
                        'full'    => 'Full Width (edge to edge)',
                    ]],
                    ['key' => 'gap', 'label' => 'Gap Between Cards', 'type' => 'select', 'options' => [
                        'tight'  => 'Tight',
                        'normal' => 'Normal (default)',
                        'wide'   => 'Wider',
                    ]],
                    ['key' => 'padding', 'label' => 'Section Vertical Spacing', 'type' => 'select', 'options' => [
                        'sm' => 'Small',
                        'md' => 'Medium (default)',
                        'lg' => 'Large',
                        'none' => 'None',
                    ]],
                ],
            ],
            'category_tiles' => [
                'label' => 'Category Tiles',
                'icon' => '🗂',
                'fields' => [
                    ['key' => 'heading', 'label' => 'Section Title', 'type' => 'text'],
                    ['key' => 'ref', 'label' => 'Categories (search and choose; empty = all)', 'type' => 'multipicker', 'source' => 'categories'],
                    ['key' => 'limit', 'label' => 'Number to Show (empty = all)', 'type' => 'number'],
                    ['key' => 'fit', 'label' => 'Image Display Mode', 'type' => 'select', 'options' => [
                        'cover' => 'Circle with background (photo)',
                        'contain' => 'Full display, no crop (transparent PNG, no frame)',
                    ]],
                    ['key' => 'layout', 'label' => 'Layout', 'type' => 'select', 'options' => [
                        'wrap' => 'Fixed Grid (default)',
                        'marquee' => 'Infinite Slider (Marquee)',
                    ]],
                    ['key' => 'speed', 'label' => 'Slider Speed (Marquee mode only)', 'type' => 'select', 'options' => [
                        'slow' => 'Slow',
                        'med' => 'Medium',
                        'fast' => 'Fast',
                    ]],
                    ['key' => 'columns_mobile', 'label' => 'Columns — Mobile (fixed grid)', 'type' => 'select', 'options' => [
                        '2' => '2', '3' => '3', '4' => '4',
                    ]],
                    ['key' => 'columns_desktop', 'label' => 'Columns — Desktop (fixed grid)', 'type' => 'select', 'options' => [
                        '4' => '4', '5' => '5', '6' => '6', '7' => '7', '8' => '8',
                    ]],
                    ['key' => 'container', 'label' => 'Section Width', 'type' => 'select', 'options' => [
                        'default' => 'Default', 'wide' => 'Wide', 'full' => 'Full Width',
                    ]],
                    ['key' => 'padding', 'label' => 'Section Vertical Spacing', 'type' => 'select', 'options' => [
                        'none' => 'None', 'sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large',
                    ]],
                ],
            ],
            'collection_scroller' => [
                'label' => 'Collection Slider (Large Image)',
                'icon' => '🖼',
                'fields' => [
                    ['key' => 'heading', 'label' => 'Section Title (optional)', 'type' => 'text'],
                    ['key' => 'ref', 'label' => 'Collections (search and choose; empty = all)', 'type' => 'multipicker', 'source' => 'collections'],
                    ['key' => 'limit', 'label' => 'Count (empty = all)', 'type' => 'number'],
                    ['key' => 'size', 'label' => 'Image Size', 'type' => 'select', 'options' => [
                        'md' => 'Medium', 'lg' => 'Large', 'xl' => 'Extra Large',
                    ]],
                    ['key' => 'shape', 'label' => 'Image Ratio', 'type' => 'select', 'options' => [
                        'portrait' => 'Portrait (3:4)', 'square' => 'Square', 'landscape' => 'Landscape (4:3)',
                    ]],
                    ['key' => 'fit', 'label' => 'Image Display Mode', 'type' => 'select', 'options' => [
                        'cover' => 'Fill the frame (photo)',
                        'contain' => 'Full display, no crop (transparent PNG)',
                    ]],
                    ['key' => 'show_name', 'label' => 'Show Name over Image', 'type' => 'select', 'options' => [
                        'no' => 'Image only', 'yes' => 'With collection name',
                    ]],
                    ['key' => 'layout', 'label' => 'Layout', 'type' => 'select', 'options' => [
                        'scroll' => 'Manual Scroll (default)',
                        'marquee' => 'Infinite Slider (Marquee)',
                    ]],
                    ['key' => 'speed', 'label' => 'Slider Speed (Marquee mode only)', 'type' => 'select', 'options' => [
                        'slow' => 'Slow',
                        'med' => 'Medium',
                        'fast' => 'Fast',
                    ]],
                    ['key' => 'container', 'label' => 'Section Width', 'type' => 'select', 'options' => [
                        'default' => 'Default', 'wide' => 'Wide', 'full' => 'Full Width',
                    ]],
                    ['key' => 'gap', 'label' => 'Gap Between Cards', 'type' => 'select', 'options' => [
                        'tight' => 'Tight', 'normal' => 'Normal', 'wide' => 'Wider',
                    ]],
                    ['key' => 'padding', 'label' => 'Section Vertical Spacing', 'type' => 'select', 'options' => [
                        'none' => 'None', 'sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large',
                    ]],
                ],
            ],
            'promo_banner' => [
                'label' => 'Promo Banner',
                'icon' => '📣',
                'fields' => [
                    ['key' => 'heading', 'label' => 'Title', 'type' => 'text'],
                    ['key' => 'text', 'label' => 'Text', 'type' => 'textarea'],
                    ['key' => 'cta_text', 'label' => 'Button Text', 'type' => 'text'],
                    ['key' => 'cta_link', 'label' => 'Button Link', 'type' => 'text'],
                    ['key' => 'container', 'label' => 'Section Width', 'type' => 'select', 'options' => [
                        'default' => 'Default', 'wide' => 'Wide', 'full' => 'Full Width',
                    ]],
                    ['key' => 'padding', 'label' => 'Section Vertical Spacing', 'type' => 'select', 'options' => [
                        'none' => 'None', 'sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large',
                    ]],
                    ['key' => 'style', 'label' => 'Background', 'type' => 'select', 'options' => [
                        'grad-red-light' => 'Clay Gradient', 'grad-dark-red' => 'Dark Clay Gradient', 'grad-dark-light' => 'Dark Gradient', 'dark' => 'Dark', 'light' => 'Light',
                    ]],
                ],
            ],
            'rich_text' => [
                'label' => 'Text and Heading',
                'icon' => '📝',
                'fields' => [
                    ['key' => 'heading', 'label' => 'Heading', 'type' => 'text'],
                    ['key' => 'body', 'label' => 'Body (HTML allowed)', 'type' => 'richtext'],
                    ['key' => 'align', 'label' => 'Alignment', 'type' => 'select', 'options' => ['right' => 'Right', 'center' => 'Center']],
                    ['key' => 'width', 'label' => 'Text Width', 'type' => 'select', 'options' => [
                        'narrow' => 'Narrow (comfortable reading)', 'default' => 'Default', 'wide' => 'Wide', 'full' => 'Full Width',
                    ]],
                    ['key' => 'padding', 'label' => 'Section Vertical Spacing', 'type' => 'select', 'options' => [
                        'none' => 'None', 'sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large',
                    ]],
                ],
            ],
            'html' => [
                'label' => 'HTML Code (Full Render)',
                'icon' => '⟨⟩',
                'fields' => [
                    ['key' => 'html', 'label' => 'HTML code — rendered exactly as you write it', 'type' => 'code'],
                    ['key' => 'width', 'label' => 'Content Width', 'type' => 'select', 'options' => [
                        'narrow' => 'Narrow (good for text)', 'wide' => 'Wide', 'full' => 'Full Width',
                    ]],
                ],
            ],
            'image' => [
                'label' => 'Image',
                'icon' => '🏞',
                'fields' => [
                    ['key' => 'image', 'label' => 'Image', 'type' => 'image'],
                    ['key' => 'link', 'label' => 'Link', 'type' => 'text'],
                    ['key' => 'caption', 'label' => 'Caption', 'type' => 'text'],
                    ['key' => 'height', 'label' => 'Height', 'type' => 'select', 'options' => ['sm' => 'Short', 'md' => 'Medium', 'lg' => 'Tall']],
                ],
            ],
            'image_duo' => [
                'label' => 'Two Images Side by Side',
                'icon' => '🖼🖼',
                'fields' => [
                    ['key' => 'image_a', 'label' => 'Image 1', 'type' => 'image'],
                    ['key' => 'link_a', 'label' => 'Link 1', 'type' => 'text'],
                    ['key' => 'image_b', 'label' => 'Image 2', 'type' => 'image'],
                    ['key' => 'link_b', 'label' => 'Link 2', 'type' => 'text'],
                    ['key' => 'stack_mobile', 'label' => 'Mobile Layout', 'type' => 'select', 'options' => [
                        'stack' => 'Stacked (one above the other)', 'side' => 'Side by side (smaller)',
                    ]],
                    ['key' => 'aspect', 'label' => 'Image Ratio', 'type' => 'select', 'options' => [
                        'portrait' => 'Portrait 4:5', 'tall' => 'Tall 2:3', 'square' => 'Square', 'wide' => 'Landscape 4:3',
                    ]],
                    ['key' => 'container', 'label' => 'Section Width', 'type' => 'select', 'options' => [
                        'default' => 'Default', 'wide' => 'Wide', 'full' => 'Full Width',
                    ]],
                    ['key' => 'gap', 'label' => 'Gap Between', 'type' => 'select', 'options' => [
                        'tight' => 'Tight', 'normal' => 'Normal', 'wide' => 'Wider',
                    ]],
                    ['key' => 'padding', 'label' => 'Section Vertical Spacing', 'type' => 'select', 'options' => [
                        'none' => 'None', 'sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large',
                    ]],
                ],
            ],
            'marquee' => [
                'label' => 'Scrolling Marquee',
                'icon' => '🔁',
                'fields' => [
                    ['key' => 'items', 'label' => 'Phrases (one per line)', 'type' => 'lines'],
                    ['key' => 'style', 'label' => 'Color', 'type' => 'select', 'options' => ['dark' => 'Dark', 'red' => 'Clay']],
                ],
            ],
            'features' => [
                'label' => 'Features / Guarantees',
                'icon' => '✅',
                'fields' => [
                    ['key' => 'heading', 'label' => 'Section Title', 'type' => 'text'],
                    ['key' => 'items', 'label' => 'Items (each line: title :: description)', 'type' => 'lines'],
                    ['key' => 'container', 'label' => 'Section Width', 'type' => 'select', 'options' => [
                        'default' => 'Default', 'wide' => 'Wide', 'full' => 'Full Width',
                    ]],
                    ['key' => 'padding', 'label' => 'Section Vertical Spacing', 'type' => 'select', 'options' => [
                        'none' => 'None', 'sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large',
                    ]],
                ],
            ],
            'concept_stores' => [
                'label' => 'Concept Stores',
                'icon' => '🏬',
                'fields' => [
                    ['key' => 'heading', 'label' => 'Section Title', 'type' => 'text'],
                    ['key' => 'subtitle', 'label' => 'Section Subtitle', 'type' => 'textarea'],
                    ['key' => 'stores', 'label' => 'Concept Stores', 'type' => 'repeater', 'sub' => [
                        ['key' => 'name', 'label' => 'Store Name', 'type' => 'text'],
                        ['key' => 'tagline', 'label' => 'Tagline / Short Title', 'type' => 'text'],
                        ['key' => 'city', 'label' => 'City / Location', 'type' => 'text'],
                        ['key' => 'description', 'label' => 'Full Description', 'type' => 'richtext'],
                        ['key' => 'image', 'label' => 'Main Image', 'type' => 'image'],
                        ['key' => 'image2', 'label' => 'Second Image (optional)', 'type' => 'image'],
                        ['key' => 'instagram', 'label' => 'Instagram (handle or link)', 'type' => 'text'],
                        ['key' => 'website', 'label' => 'Website (optional)', 'type' => 'text'],
                    ]],
                    ['key' => 'container', 'label' => 'Section Width', 'type' => 'select', 'options' => [
                        'default' => 'Default', 'wide' => 'Wide', 'full' => 'Full Width',
                    ]],
                    ['key' => 'padding', 'label' => 'Section Vertical Spacing', 'type' => 'select', 'options' => [
                        'none' => 'None', 'sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large',
                    ]],
                ],
            ],
            'newsletter' => [
                'label' => 'Newsletter Signup',
                'icon' => '✉️',
                'fields' => [
                    ['key' => 'container', 'label' => 'Section Width', 'type' => 'select', 'options' => [
                        'default' => 'Default', 'wide' => 'Wide', 'full' => 'Full Width',
                    ]],
                    ['key' => 'padding', 'label' => 'Section Vertical Spacing', 'type' => 'select', 'options' => [
                        'none' => 'None', 'sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large',
                    ]],
                    ['key' => 'heading', 'label' => 'Heading', 'type' => 'text'],
                    ['key' => 'text', 'label' => 'Text', 'type' => 'textarea'],
                    ['key' => 'channel', 'label' => 'Channel', 'type' => 'select', 'options' => ['telegram' => 'Telegram', 'sms' => 'SMS', 'email' => 'Email']],
                ],
            ],
            'pattern_divider' => [
                'label' => 'Motif Divider',
                'icon' => '➗',
                'fields' => [
                    ['key' => 'style', 'label' => 'Color', 'type' => 'select', 'options' => ['light' => 'Light', 'red' => 'Clay', 'dark' => 'Dark']],
                    ['key' => 'height', 'label' => 'Height', 'type' => 'select', 'options' => ['sm' => 'Short', 'md' => 'Medium']],
                ],
            ],
            'lookbook_grid' => [
                'label' => 'Lookbook (Mosaic Grid)',
                'icon' => '🧱',
                'fields' => [
                    ['key' => 'heading', 'label' => 'Section Title', 'type' => 'text'],
                    ['key' => 'subtitle', 'label' => 'Subtitle (optional)', 'type' => 'text'],
                    ['key' => 'products', 'label' => 'Selected Products (search and choose; empty = use the source below)', 'type' => 'multipicker', 'source' => 'products'],
                    ['key' => 'feed', 'label' => 'Product Source (when no products are selected — category or collection)', 'type' => 'picker', 'source' => 'product_feed'],
                    ['key' => 'limit', 'label' => 'Number to Show (empty = 12)', 'type' => 'number'],
                    ['key' => 'columns', 'label' => 'Columns (desktop)', 'type' => 'select', 'options' => [
                        '3' => '3 Columns', '4' => '4 Columns', '5' => '5 Columns',
                    ]],
                    ['key' => 'mobile_columns', 'label' => 'Columns (mobile)', 'type' => 'select', 'options' => [
                        '2' => '2 Columns', '3' => '3 Columns',
                    ]],
                    ['key' => 'gap', 'label' => 'Gap Between Tiles', 'type' => 'select', 'options' => [
                        'tight' => 'Tight', 'normal' => 'Normal', 'wide' => 'Wider',
                    ]],
                    ['key' => 'container', 'label' => 'Section Width', 'type' => 'select', 'options' => [
                        'default' => 'Default', 'wide' => 'Wide', 'full' => 'Full Width',
                    ]],
                    ['key' => 'padding', 'label' => 'Section Vertical Spacing', 'type' => 'select', 'options' => [
                        'none' => 'None', 'sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large',
                    ]],
                ],
            ],
            'editorial_hero' => [
                'label' => 'Editorial Hero / Banner (Slider)',
                'icon' => '🎞',
                'fields' => [
                    // Block-level: height + autoplay + global overlay style
                    ['key' => 'height', 'label' => 'Height', 'type' => 'select', 'options' => [
                        'natural' => 'Natural — the image\'s own size (no crop)',
                        'strip'   => 'Strip (20vh — thin banner)',
                        'short'   => 'Short (40vh — medium banner)',
                        'banner'  => 'Banner (55vh)',
                        'med'     => 'Medium (75vh)',
                        'tall'    => 'Full Screen (100vh — full hero, portrait on mobile)',
                        'custom'  => 'Custom — set with the slider below',
                    ]],
                    ['key' => 'height_custom', 'label' => 'Custom Height (only when "Custom" is selected)', 'type' => 'range', 'min' => 10, 'max' => 120, 'default' => 55, 'unit' => 'vh'],
                    ['key' => 'interval', 'label' => 'Slide Transition Interval (seconds; 0 = no autoplay)', 'type' => 'number'],
                    ['key' => 'overlay', 'label' => 'Dark Overlay on Images (shared by all slides)', 'type' => 'select', 'options' => [
                        'none' => 'None',
                        'soft' => 'Soft (20%)',
                        'medium' => 'Medium (35%)',
                        'strong' => 'Strong (50%)',
                    ]],

                    // ----- Full-bleed background layer (block-level) -----
                    // bg_color paints the entire block; bg_image sits over it
                    // at bg_opacity %; bg_blend optionally mixes them.
                    ['key' => 'bg_color', 'label' => 'Block Background Color (behind the image)', 'type' => 'color'],
                    ['key' => 'bg_image', 'label' => 'Block Background Image (optional — behind the slides)', 'type' => 'image'],
                    ['key' => 'bg_opacity', 'label' => 'Background Image Opacity', 'type' => 'range', 'min' => 0, 'max' => 100, 'default' => 100, 'unit' => '%'],

                    // ----- Floating PNG overlay (block-level) -----
                    // Sits ABOVE the slide image and overlay; positioned by
                    // x/y % per breakpoint so the admin can drop a logo,
                    // sticker or seal anywhere on the banner.
                    ['key' => 'overlay_png', 'label' => 'Image over Banner (floating PNG — optional)', 'type' => 'image'],
                    ['key' => 'overlay_png_link', 'label' => 'Link on PNG (optional — click on the floating image)', 'type' => 'text'],
                    ['key' => 'overlay_width', 'label' => 'Floating PNG Width (% of block width)', 'type' => 'range', 'min' => 1, 'max' => 100, 'default' => 25, 'unit' => '%'],
                    // Position unit — percent (%, scales with the banner) or pixels (px,
                    // exact). Drag the PNG on the canvas or type an exact value.
                    ['key' => 'overlay_unit', 'label' => 'PNG Position Unit', 'type' => 'select', 'options' => ['%' => 'Percent (%)', 'px' => 'Pixels (px)']],
                    ['key' => 'overlay_x_desktop', 'label' => 'PNG Horizontal Position (desktop)', 'type' => 'number'],
                    ['key' => 'overlay_y_desktop', 'label' => 'PNG Vertical Position (desktop)', 'type' => 'number'],
                    ['key' => 'overlay_x_mobile', 'label' => 'PNG Horizontal Position (mobile)', 'type' => 'number'],
                    ['key' => 'overlay_y_mobile', 'label' => 'PNG Vertical Position (mobile)', 'type' => 'number'],

                    // Per-slide: image (desktop) + image_mobile + all overlay
                    // elements + per-breakpoint position and font size.
                    ['key' => 'slides', 'label' => 'Slides (each slide has its own image, text and position)', 'type' => 'repeater', 'sub' => [
                        ['key' => 'image', 'label' => 'Desktop Image (landscape)', 'type' => 'image'],
                        ['key' => 'image_mobile', 'label' => 'Mobile Image (portrait — optional; empty = same as desktop image)', 'type' => 'image'],
                        ['key' => 'focal', 'label' => 'Image Focal Point (which part stays in view when the photo is cropped to fill)', 'type' => 'select', 'options' => [
                            'center' => 'Center', 'top' => 'Top', 'bottom' => 'Bottom', 'left' => 'Left', 'right' => 'Right',
                            'top-left' => 'Top-left', 'top-right' => 'Top-right', 'bottom-left' => 'Bottom-left', 'bottom-right' => 'Bottom-right',
                        ]],
                        ['key' => 'link', 'label' => 'Link for Entire Image (optional — click anywhere on the slide)', 'type' => 'text'],
                        ['key' => 'kicker', 'label' => 'Kicker (e.g. "Now Live")', 'type' => 'text'],
                        ['key' => 'title', 'label' => 'Large Title', 'type' => 'text'],
                        ['key' => 'subtitle', 'label' => 'Small Subtitle', 'type' => 'text'],
                        ['key' => 'cta_text', 'label' => 'Button Text', 'type' => 'text'],
                        ['key' => 'cta_link', 'label' => 'Button Link', 'type' => 'text'],
                        ['key' => 'ends_at', 'label' => 'Countdown Timer (end date and time)', 'type' => 'datetime'],
                        ['key' => 'text_color', 'label' => 'Text Color', 'type' => 'select', 'options' => [
                            'white' => 'White', 'dark' => 'Dark',
                        ]],
                        ['key' => 'title_size', 'label' => 'Title Size', 'type' => 'select', 'options' => [
                            'sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large', 'xl' => 'Huge',
                        ]],
                        ['key' => 'position_desktop', 'label' => 'Text Position (desktop)', 'type' => 'select', 'options' => [
                            'tl' => '↖ Top Left', 'tc' => '↑ Top Center', 'tr' => '↗ Top Right',
                            'ml' => '← Middle Left', 'mc' => '○ Center',          'mr' => '→ Middle Right',
                            'bl' => '↙ Bottom Left','bc' => '↓ Bottom Center','br' => '↘ Bottom Right',
                        ]],
                        ['key' => 'position_mobile', 'label' => 'Text Position (mobile)', 'type' => 'select', 'options' => [
                            'tl' => '↖ Top Left', 'tc' => '↑ Top Center', 'tr' => '↗ Top Right',
                            'ml' => '← Middle Left', 'mc' => '○ Center',          'mr' => '→ Middle Right',
                            'bl' => '↙ Bottom Left','bc' => '↓ Bottom Center','br' => '↘ Bottom Right',
                        ]],
                        // ---- Font + overlay + CTA styling per slide ----
                        ['key' => 'title_font', 'label' => 'Title Font', 'type' => 'select', 'options' => [
                            'sans'    => 'Vazir — Persian text',
                            'display' => 'Jost — Latin/editorial',
                            'mono'    => 'JetBrains Mono — typewriter',
                        ]],
                        ['key' => 'title_weight', 'label' => 'Title Weight', 'type' => 'select', 'options' => [
                            'light' => 'Light', 'normal' => 'Normal', 'semibold' => 'Semibold', 'bold' => 'Bold', 'extrabold' => 'Extra Bold',
                        ]],
                        ['key' => 'title_tracking', 'label' => 'Title Letter Spacing', 'type' => 'select', 'options' => [
                            'tight' => 'Tight', 'normal' => 'Normal', 'wide' => 'Wide', 'wider' => 'Wider',
                        ]],
                        ['key' => 'kicker_font', 'label' => 'Kicker Font', 'type' => 'select', 'options' => [
                            'sans-italic'    => 'Vazir Italic',
                            'display-italic' => 'Jost Italic',
                            'display-upper'  => 'Jost Uppercase',
                        ]],
                        // Per-slide overlay tint + opacity (overrides the block-level overlay for this slide)
                        ['key' => 'slide_overlay_color', 'label' => 'Overlay Color on Image', 'type' => 'select', 'options' => [
                            'none' => 'None', 'dark' => 'Dark', 'light' => 'Light', 'red' => 'Brand Clay', 'gradient-bottom' => 'Gradient from Bottom',
                        ]],
                        ['key' => 'slide_overlay_opacity', 'label' => 'Overlay Opacity', 'type' => 'select', 'options' => [
                            '0' => '0%', '20' => '20%', '35' => '35%', '50' => '50%', '70' => '70%', '85' => '85%',
                        ]],
                        // CTA appearance — underlined link or filled pill
                        ['key' => 'cta_style', 'label' => 'CTA Button Style', 'type' => 'select', 'options' => [
                            'underline' => 'Underlined Link (editorial)',
                            'pill-light' => 'Light Button (filled)',
                            'pill-dark'  => 'Dark Button (filled)',
                            'pill-accent'=> 'Brand Clay Button',
                            'ghost'      => 'Ghost Button (no fill)',
                        ]],
                        // Padding inside the slide for the overlay text container
                        ['key' => 'text_padding', 'label' => 'Text Spacing from Image Edge', 'type' => 'select', 'options' => [
                            'tight' => 'Tight', 'normal' => 'Normal', 'loose' => 'Looser',
                        ]],
                    ]],
                ],
            ],
            'collection_feature' => [
                'label' => 'Collection Feature (Editorial)',
                'icon' => '📰',
                'fields' => [
                    ['key' => 'collection', 'label' => 'Collection (search and choose)', 'type' => 'picker', 'source' => 'collections'],
                    ['key' => 'eyebrow', 'label' => 'Small Label (e.g. RACKET CLUB COLLECTION)', 'type' => 'text'],
                    ['key' => 'cta_text', 'label' => 'Button Text (default: "View All")', 'type' => 'text'],

                    // Manual product override: if set, these exact products show
                    // in this exact order instead of the collection's auto-feed.
                    ['key' => 'products', 'label' => 'Selected Products (empty = newest from this collection)', 'type' => 'multipicker', 'source' => 'products'],
                    ['key' => 'limit', 'label' => 'Number of Products Beside the Text (2 to 4)', 'type' => 'number'],
                    ['key' => 'show_summary', 'label' => 'Show Short Description Under Each Product', 'type' => 'select', 'options' => [
                        'no' => 'No',
                        'yes' => 'Yes',
                    ]],

                    // Image: override + size knob. Override beats the collection's image_path.
                    ['key' => 'image_override', 'label' => 'Custom Image (optional — replaces the collection image)', 'type' => 'image'],
                    ['key' => 'image_size', 'label' => 'Image Size', 'type' => 'select', 'options' => [
                        'sm' => 'Small (4:5)',
                        'md' => 'Medium (3:4)',
                        'lg' => 'Large (2:3)',
                        'square' => 'Square (1:1)',
                        'wide'  => 'Landscape (4:3)',
                    ]],
                    ['key' => 'image_side', 'label' => 'Image Position (desktop)', 'type' => 'select', 'options' => [
                        'start' => 'Start Side (default RTL)',
                        'end' => 'End Side',
                    ]],
                    ['key' => 'image_size_mobile', 'label' => 'Image Size — Mobile', 'type' => 'select', 'options' => [
                        'sm' => 'Small 4:5', 'md' => 'Medium 3:4', 'lg' => 'Large 2:3', 'square' => 'Square', 'wide' => 'Landscape 4:3',
                    ]],
                    ['key' => 'tone', 'label' => 'Diagonal Cut Color', 'type' => 'select', 'options' => [
                        'red' => 'Club Clay',
                        'dark' => 'Club Navy',
                    ]],
                    ['key' => 'corner', 'label' => 'Cut Corner', 'type' => 'select', 'options' => [
                        'bottom' => 'Bottom',
                        'top' => 'Top',
                    ]],
                    ['key' => 'container', 'label' => 'Section Width', 'type' => 'select', 'options' => [
                        'default' => 'Default', 'wide' => 'Wide', 'full' => 'Full Width',
                    ]],
                    ['key' => 'padding', 'label' => 'Section Vertical Spacing', 'type' => 'select', 'options' => [
                        'none' => 'None', 'sm' => 'Small', 'md' => 'Medium', 'lg' => 'Large',
                    ]],
                ],
            ],
            'velour_banner' => [
                'label' => 'Velour Banner (Parallax)',
                'icon' => '🫧',
                'fields' => [
                    ['key' => 'image', 'label' => 'Banner Image', 'type' => 'image'],
                    ['key' => 'image_x', 'label' => 'Image Horizontal Position (0=left to 100=right)', 'type' => 'range', 'min' => 0, 'max' => 100, 'default' => 50, 'unit' => '%'],
                    ['key' => 'image_y', 'label' => 'Image Vertical Position (0=top to 100=bottom)', 'type' => 'range', 'min' => 0, 'max' => 100, 'default' => 44, 'unit' => '%'],
                    ['key' => 'image_zoom', 'label' => 'Image Zoom', 'type' => 'range', 'min' => 100, 'max' => 220, 'default' => 132, 'unit' => '%'],
                    ['key' => 'image_mobile', 'label' => 'Alternate Image for Mobile (optional)', 'type' => 'image'],
                    ['key' => 'kicker', 'label' => 'Kicker (e.g. "Featured Collection")', 'type' => 'text'],
                    ['key' => 'title', 'label' => 'Title (first line)', 'type' => 'text'],
                    ['key' => 'title_em', 'label' => 'Italic Title (second line — optional)', 'type' => 'text'],
                    ['key' => 'meta', 'label' => 'Bottom Text (one per line)', 'type' => 'lines'],
                    ['key' => 'link', 'label' => 'Banner Link (optional — makes the whole banner clickable)', 'type' => 'text'],
                    ['key' => 'accent', 'label' => 'Accent Color (gold by default)', 'type' => 'color'],
                    ['key' => 'caption_position', 'label' => 'Text Position', 'type' => 'select', 'options' => [
                        'right' => 'Right', 'left' => 'Left', 'center' => 'Center',
                    ]],
                    ['key' => 'height', 'label' => 'Height', 'type' => 'select', 'options' => [
                        'strip'  => 'Strip (20vh)',
                        'short'  => 'Short (40vh)',
                        'banner' => 'Banner (55vh)',
                        'med'    => 'Medium (75vh)',
                        'tall'   => 'Full Screen (100vh)',
                        'custom' => 'Custom — with the slider below',
                    ]],
                    ['key' => 'height_custom', 'label' => 'Custom Height (only when "Custom" is selected)', 'type' => 'range', 'min' => 10, 'max' => 120, 'default' => 40, 'unit' => 'vh'],
                    ['key' => 'bubbles', 'label' => 'Floating Bubbles', 'type' => 'select', 'options' => [
                        'subtle' => 'Subtle', 'rich' => 'Rich', 'none' => 'None',
                    ]],
                    ['key' => 'parallax', 'label' => 'Mouse Parallax Motion', 'type' => 'select', 'options' => [
                        'on' => 'On', 'off' => 'Off',
                    ]],
                ],
            ],
            'countdown_banner' => [
                'label' => 'Countdown Banner',
                'icon' => '⏳',
                'fields' => [
                    ['key' => 'heading', 'label' => 'Title', 'type' => 'text'],
                    ['key' => 'subtitle', 'label' => 'Subtitle', 'type' => 'text'],
                    ['key' => 'ends_at', 'label' => 'End (YYYY-MM-DD HH:MM, Tehran timezone)', 'type' => 'text'],
                    ['key' => 'cta_text', 'label' => 'Button Text', 'type' => 'text'],
                    ['key' => 'cta_link', 'label' => 'Button Link', 'type' => 'text'],
                    ['key' => 'style', 'label' => 'Background', 'type' => 'select', 'options' => [
                        'grad-dark-red' => 'Dark Clay Gradient', 'grad-red-light' => 'Clay Gradient', 'dark' => 'Dark', 'amber' => 'Amber',
                    ]],
                ],
            ],
        ];
    }

    public static function types(): array
    {
        return array_keys(self::all());
    }

    public static function exists(string $type): bool
    {
        return isset(self::all()[$type]);
    }

    public static function fields(string $type): array
    {
        return self::all()[$type]['fields'] ?? [];
    }

    public static function label(string $type): string
    {
        return self::all()[$type]['label'] ?? $type;
    }

    /**
     * Inline CSS for a block's wrapper from its universal `_style` layer.
     * Returns 'display:contents' when there are no overrides, so the live
     * layout is byte-identical for blocks that don't use the style controls.
     */
    public static function wrapStyle(array $data): string
    {
        $s = (array) ($data['_style'] ?? []);
        $css = [];
        foreach (['pt' => 'padding-top', 'pb' => 'padding-bottom', 'mt' => 'margin-top', 'mb' => 'margin-bottom'] as $k => $prop) {
            if (($s[$k] ?? '') !== '') {
                $css[] = $prop.':'.(int) $s[$k].'px';
            }
        }
        if (($s['px'] ?? '') !== '') {
            $css[] = 'padding-inline:'.(int) $s['px'].'px';
        }
        if ((int) ($s['maxw'] ?? 0) > 0) {
            $css[] = 'max-width:'.(int) $s['maxw'].'px';
        }
        if (($s['align'] ?? '') === 'center') {
            $css[] = 'margin-inline:auto';
        }
        if ((int) ($s['minh'] ?? 0) > 0) {
            $css[] = 'min-height:'.(int) $s['minh'].'px';
        }
        if ((int) ($s['radius'] ?? 0) > 0) {
            $css[] = 'border-radius:'.(int) $s['radius'].'px';
            $css[] = 'overflow:hidden';
        }
        $bw = (int) ($s['bw'] ?? 0);
        if ($bw > 0) {
            $css[] = 'border:'.$bw.'px solid '.self::cssColor($s['bc'] ?? '#e5e7eb');
        }
        $shadow = [
            'sm' => '0 1px 2px rgba(0,0,0,.08)',
            'md' => '0 6px 18px rgba(0,0,0,.10)',
            'lg' => '0 20px 45px rgba(0,0,0,.16)',
        ][$s['shadow'] ?? ''] ?? '';
        if ($shadow !== '') {
            $css[] = 'box-shadow:'.$shadow;
        }
        if (in_array($s['ta'] ?? '', ['right', 'center', 'left'], true)) {
            $css[] = 'text-align:'.$s['ta'];
        }
        if (($s['bg'] ?? '') !== '') {
            $css[] = 'background-color:'.self::cssColor($s['bg']);
        }

        return $css ? implode(';', $css) : 'display:contents';
    }

    /** Sanitise an admin-supplied colour for inline CSS. */
    private static function cssColor(string $c): string
    {
        return preg_replace('/[^#a-zA-Z0-9(),.%\s]/', '', $c);
    }

    /**
     * Typography declarations from `_style` (colour, weight, line-height,
     * letter-spacing), applied scoped to a block's headings/paragraphs with
     * !important so they override the block's Tailwind utility classes.
     * Empty string when nothing is set.
     */
    public static function typographyStyle(array $data): string
    {
        $s = (array) ($data['_style'] ?? []);
        $css = [];
        if (($s['tc'] ?? '') !== '') {
            $css[] = 'color:'.self::cssColor($s['tc']).' !important';
        }
        if (in_array((string) ($s['fw'] ?? ''), ['300', '400', '500', '600', '700', '800'], true)) {
            $css[] = 'font-weight:'.$s['fw'].' !important';
        }
        if (($s['lh'] ?? '') !== '') {
            $lh = preg_replace('/[^0-9.]/', '', (string) $s['lh']);
            if ($lh !== '') {
                $css[] = 'line-height:'.$lh.' !important';
            }
        }
        if (($s['ls'] ?? '') !== '') {
            $css[] = 'letter-spacing:'.(int) $s['ls'].'px !important';
        }

        return implode(';', $css);
    }

    /**
     * Mobile (<768px) CSS declarations from the `_style.m` overrides — emitted
     * inside a media query keyed on the block's data-bid. Uses !important so it
     * beats the inline desktop styles. Empty string when there are no overrides.
     */
    public static function wrapStyleMobile(array $data): string
    {
        $m = (array) ($data['_style']['m'] ?? []);
        $css = [];
        foreach (['pt' => 'padding-top', 'pb' => 'padding-bottom', 'mt' => 'margin-top', 'mb' => 'margin-bottom'] as $k => $prop) {
            if (($m[$k] ?? '') !== '') {
                $css[] = $prop.':'.(int) $m[$k].'px !important';
            }
        }
        if (($m['px'] ?? '') !== '') {
            $css[] = 'padding-inline:'.(int) $m['px'].'px !important';
        }
        if ((int) ($m['maxw'] ?? 0) > 0) {
            $css[] = 'max-width:'.(int) $m['maxw'].'px !important';
        }
        if ((int) ($m['minh'] ?? 0) > 0) {
            $css[] = 'min-height:'.(int) $m['minh'].'px !important';
        }
        if (in_array($m['ta'] ?? '', ['right', 'center', 'left'], true)) {
            $css[] = 'text-align:'.$m['ta'].' !important';
        }
        if (($m['bg'] ?? '') !== '') {
            $css[] = 'background-color:'.self::cssColor($m['bg']).' !important';
        }

        return implode(';', $css);
    }

    /** Mobile typography declarations (colour/weight/line-height/letter-spacing). */
    public static function typographyStyleMobile(array $data): string
    {
        $m = (array) ($data['_style']['m'] ?? []);
        $css = [];
        if (($m['tc'] ?? '') !== '') {
            $css[] = 'color:'.self::cssColor($m['tc']).' !important';
        }
        if (in_array((string) ($m['fw'] ?? ''), ['300', '400', '500', '600', '700', '800'], true)) {
            $css[] = 'font-weight:'.$m['fw'].' !important';
        }
        if (($m['lh'] ?? '') !== '') {
            $lh = preg_replace('/[^0-9.]/', '', (string) $m['lh']);
            if ($lh !== '') {
                $css[] = 'line-height:'.$lh.' !important';
            }
        }
        if (($m['ls'] ?? '') !== '') {
            $css[] = 'letter-spacing:'.(int) $m['ls'].'px !important';
        }

        return implode(';', $css);
    }

    /**
     * Keep only the keys this block type declares; coerce numeric/lines fields.
     *
     * @param  array<string,mixed>  $data
     * @return array<string,mixed>
     */
    public static function sanitize(string $type, array $data): array
    {
        $clean = [];
        foreach (self::fields($type) as $field) {
            $key = $field['key'];
            $val = $data[$key] ?? null;
            if (($field['type'] ?? null) === 'repeater') {
                $clean[$key] = self::sanitizeRepeater($field['sub'] ?? [], $val);
            } else {
                $clean[$key] = self::coerce($field['type'], $val);
                // Purify richtext fields
                if ($field['type'] === 'richtext' && is_string($clean[$key])) {
                    $clean[$key] = self::getPurifier()->purify($clean[$key]);
                }
            }
        }

        // Universal per-block style layer (Elementor-style «پیشرفته» controls) —
        // whitelisted keys, applied as inline CSS on the block wrapper at render.
        if (isset($data['_style']) && is_array($data['_style'])) {
            $st = [];
            foreach (['pt', 'pb', 'px', 'mt', 'mb', 'maxw', 'minh', 'radius', 'bw', 'ls'] as $k) {
                $v = $data['_style'][$k] ?? null;
                if ($v !== null && $v !== '') {
                    $st[$k] = max(0, (int) $v);
                }
            }
            foreach (['align', 'bg', 'bc', 'shadow', 'ta', 'tc', 'fw', 'lh'] as $k) {
                $v = trim((string) ($data['_style'][$k] ?? ''));
                if ($v !== '') {
                    $st[$k] = $v;
                }
            }
            // Per-breakpoint mobile overrides (spacing/size/align) under _style.m.
            if (isset($data['_style']['m']) && is_array($data['_style']['m'])) {
                $m = [];
                foreach (['pt', 'pb', 'px', 'mt', 'mb', 'maxw', 'minh', 'ls'] as $k) {
                    $v = $data['_style']['m'][$k] ?? null;
                    if ($v !== null && $v !== '') {
                        $m[$k] = max(0, (int) $v);
                    }
                }
                foreach (['ta', 'bg', 'tc', 'fw', 'lh'] as $k) {
                    $v = trim((string) ($data['_style']['m'][$k] ?? ''));
                    if ($v !== '') {
                        $m[$k] = $v;
                    }
                }
                if ($m) {
                    $st['m'] = $m;
                }
            }
            if ($st) {
                $clean['_style'] = $st;
            }
        }

        return $clean;
    }

    /** Coerce a single scalar field value by its declared type. */
    private static function coerce(string $type, mixed $val): mixed
    {
        return match ($type) {
            'number' => $val === null || $val === '' ? null : (int) $val,
            'lines' => self::toLines($val),
            'multipicker' => self::toCsv($val),
            default => is_string($val) ? trim($val) : $val,
        };
    }

    /** Normalise a multipicker value (comma string or array) into a clean,
     *  de-duplicated comma-joined slug string. */
    private static function toCsv(mixed $val): string
    {
        $items = is_array($val) ? $val : explode(',', (string) $val);
        $items = array_filter(array_map('trim', $items), fn ($v) => $v !== '');

        return implode(',', array_values(array_unique($items)));
    }

    /**
     * Sanitize a repeater value into a clean list of rows, each limited to the
     * sub-fields the repeater declares.
     *
     * @param  array<int,array>  $sub
     * @return array<int,array<string,mixed>>
     */
    private static function sanitizeRepeater(array $sub, mixed $val): array
    {
        $rows = [];
        foreach ((array) $val as $row) {
            if (! is_array($row)) {
                continue;
            }
            $clean = [];
            $empty = true;
            foreach ($sub as $field) {
                $v = self::coerce($field['type'], $row[$field['key']] ?? null);
                $clean[$field['key']] = $v;
                // Selects always carry a default, so they don't make a row "real".
                if ($field['type'] !== 'select' && $v !== null && $v !== '' && $v !== []) {
                    $empty = false;
                }
            }
            if (! $empty) {
                $rows[] = $clean;
            }
        }

        return $rows;
    }

    /** Normalise a "lines" value (textarea or array) into a clean string array. */
    private static function toLines(mixed $val): array
    {
        if (is_array($val)) {
            $lines = $val;
        } else {
            $lines = preg_split('/\r\n|\r|\n/', (string) $val) ?: [];
        }

        return array_values(array_filter(array_map('trim', $lines), fn ($l) => $l !== ''));
    }

    /** Get HTMLPurifier instance (lazy singleton). */
    private static function getPurifier(): HTMLPurifier
    {
        if (self::$purifier === null) {
            $config = HTMLPurifier_Config::createDefault();
            $config->set('HTML.Doctype', 'XHTML 1.0 Strict');
            // Note: do NOT set the bogus `URI.FilterAssets` directive — it does
            // not exist in HTMLPurifier and throws "Cannot set undefined directive",
            // which 500s every page-save with a rich_text block.
            // You can customize the allowed tags here if needed.
            // For example, to allow only a safe set:
            // $config->set('HTML.Allowed', 'p,br,b,i,strong,em,a[href],ul,ol,li,hr');
            self::$purifier = new HTMLPurifier($config);
        }
        return self::$purifier;
    }
}
