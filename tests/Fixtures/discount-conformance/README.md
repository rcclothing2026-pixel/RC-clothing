# Discount conformance fixtures

One JSON file per case: a cart goes in, the discounts that must come out. Every engine
that prices a StoqS cart runs all of them and must agree to the toman:

- StoqS itself (`includes/discount-engine.php`), run by `tests/discount-conformance-test.php`;
- the WooCommerce plugin's copy (`includes/engine/discount-engine.php`), from phase 9;
- a member's own shop (its port of the engine), from phase 10.

They will also be served read-only at `GET /api/v1/promotions/conformance` (phase 5), so a
member's developer can pin them in their own tests. The fixtures name no brand, category,
domain or real price list.

## File

`<id>.json`, where `id` is `c` plus three digits and equals the file name.

```json
{
  "id": "c025",
  "title": "One line in English: what the case proves",
  "schema": 2,
  "opts":  { "max_discount_percent": 25, "min_margin_percent": 10, "channel": "web",
             "now": "2026-11-11 12:00:00" },
  "rules": [ ...rule objects... ],
  "codes": [ ...code objects the customer entered... ],
  "lines": [ ...cart lines... ],
  "expect": { ...only the values this case pins... }
}
```

- **schema**: the lowest engine schema that gives the expected result. 1 = today's engine
  (the cap on the sale price). 2 = lines may carry `list_price` and codes `vs_list`. 3 = lines
  may carry `cap_exempt` and `count_in_tiers`. An engine runs every fixture with `schema` up to
  its own.
- **opts**: what the engine is called with: `max_discount_percent`, `min_margin_percent`,
  `channel` (`web`, `brand`, `stores`, `concept`), `now` (Tehran time; fixtures never
  depend on the clock), and optionally `customer` (null = guest) and `has_costs`.
- **rules**: the shops' stored rule shape: `id`, `label`, `priority`, `conditions[]`,
  `actions[]`, `apply_mode` (all|any), `stack_mode` (all|best), `exclusive`, `group`,
  `channels` (CSV), `starts_at`, `ends_at`, `enabled`.
- **codes**: `id`, `code`, `type` (percent|toman|free_shipping), `value`, `min_subtotal`,
  `max_subtotal`, `max_discount`, `starts_at`, `ends_at`, `usage_limit`, `uses`,
  `per_customer_limit`, `first_order_only`, `individual_use` («تنها»), `vs_list`
  (schema 2), the include/exclude product and category lists, `channels`, `enabled`.
- **lines**: `variant_id`, `product_id`, `category_ids`, `qty`, `unit_price` (what the line
  sells at, after any campaign price), and optionally `list_price` (schema 2: the price
  before the campaign), `floor_price` (precomputed; sites are sent floors, never costs),
  `cost` (StoqS-side only: the floor is computed from it with `min_margin_percent`),
  `discountable` (false = a no-discount category; it still counts toward piece tiers),
  `cap_exempt` (schema 3, default false: the item's «اجازهٔ عبور از سقف»; the line's room is its
  whole total, no % cap and no floor, and its total is added whole to the cart's cap),
  `count_in_tiers` (schema 3, default true; false = its pieces are not counted by any count
  condition, as for an outlet item set to «فقط اوتلت»; money conditions still see it).

## Expectations

`expect` pins only what is listed; anything not listed is not compared. All money is whole
toman.

| key | meaning |
|---|---|
| `subtotal`, `discountable_subtotal`, `cap`, `discount`, `total` | the cart totals |
| `cap_basis` | `"list"` when the cap was measured on list prices (schema 2) |
| `shipping_free` | a free-shipping code or rule applied |
| `rules_applied` | the ids of the rules that counted, in any order |
| `codes[]` | per entered code, in order: `code`, `valid`, `applied`, `reason`, `amount` |
| `lines[]` | per line, in order: `discount`, `room`, `floor_price`, `list_price`, `margin_ok` |

A fixture may also carry `equals_sum_of: [ids]` at the top level: its `discount` must equal
the sum of those fixtures' discounts (split-cart invariance: A+B against A alone and B
alone, where no cap couples the lines).

Every fixture also holds three invariants, listed or not: the lines add up to `discount`,
no line takes more than its room, and each line's parts add up to its discount.

## Adding a case

Work the numbers out by hand from the spec (PLAN §4) and write them down. Never paste
an engine's output in as the expectation: a fixture that copies the engine proves nothing.
