<?php

return [
    // Category slugs that coupons and discount rules never touch (winter items).
    'no_discount_categories' => array_values(array_filter(explode(',', (string) env('SHOP_NO_DISCOUNT_CATEGORIES', 'gampr')))),

    // Ceiling on coupon + rule discounts, as a percentage of the discountable
    // subtotal. 25 keeps every product above the 10% margin floor at current costs.
    'max_discount_percent' => (int) env('SHOP_MAX_DISCOUNT_PERCENT', 25),

    // Highest outlet markdown the admin may set (admin → اوتلت).
    'outlet_max_percent' => (int) env('SHOP_OUTLET_MAX_PERCENT', 30),
];
