# Racket Club — Storefront UI kit

A high-fidelity recreation of the Racket Club online store — a quiet-luxury leisurewear brand
("The Art of Leisure"). Composes the design-system components; primitives are not re-implemented.

**Entry:** `index.html` — interactive storefront.

## Sections
- **Announcement + Header** (`parts.jsx`) — thin navy promo bar; sticky cream header with centred `Logo`, uppercase tracked nav, search / account / bag (live count).
- **Hero** (`parts.jsx`) — the signature **primary gradient** (navy→forest), uppercase Mansory H1, Pinyon slogan, `accent`-gradient CTA, and an `image-slot` for the lookbook photo.
- **Products** (`blocks.jsx`) — "The Essentials" 4-up grid; portrait `image-slot`s; add-to-bag reveals on hover.
- **Editorial** (`blocks.jsx`) — split image + evocative copy ("Designed for the life after the match").
- **Collections** (`blocks.jsx`) — three tiles with gradient scrims and names.
- **Footer** (`parts.jsx`) — navy columns + slogan.
- **CartDrawer** (`blocks.jsx`) — right slide-in bag with line items, subtotal, `accent` checkout.

## Interactions
Add to bag → item added / quantity bumped, bag count updates, cart drawer slides in. Remove line items; subtotal recomputes. Nav sets active state.

## Imagery
Every photo is an **`image-slot`** — drop in real photography (soft golden-hour light, coastal + court, fabric/wood/stone textures per the CI mood board). Apply the CI's cream-overlay treatment on busy shots for legibility. Persistence works best when the page is served from the project root; in this subfolder the slots still render and accept drops for preview.
