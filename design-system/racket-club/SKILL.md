---
name: racket-club-design
description: Use this skill to generate well-branded interfaces and assets for Racket Club, a quiet-luxury leisurewear (clothing) brand, either for production or throwaway prototypes/mocks/etc. Contains essential design guidelines, colors, type, fonts, assets, and UI kit components for prototyping.
user-invocable: true
---

Read the README.md file within this skill, and explore the other available files.
If creating visual artifacts (slides, mocks, throwaway prototypes, etc), copy assets out and create static HTML files for the user to view. If working on production code, you can copy assets and read the rules here to become an expert in designing with this brand.
If the user invokes this skill without any other guidance, ask them what they want to build or design, ask some questions, and act as an expert designer who outputs HTML artifacts _or_ production code, depending on the need.

## Quick reference
- **Brand:** Racket Club — quiet-luxury **leisurewear/clothing** brand. Essence: *The Art of Leisure* (the life off the court). Slogan: *Legends & Legacy*. Archetype: The Sovereign.
- **Voice:** refined, confident, evocative — sell the moment, not the product ("Style for your leisure moments," not "Buy our t-shirt"). No emoji.
- **Colours:** Navy `#18234F`, Forest `#034326`, Clay `#A72F23`, Brick Brown `#672C24`, Warm Beige `#C6AF92`, Cream `#ECE7D0`, White. Balance 60 (cream) / 30 (navy) / 10 (accents).
- **Gradients:** primary navy→forest (225°) for heroes/headers; accent clay→brown (180°) for CTAs.
- **Type:** Mansory (headings/logo — H1 UPPERCASE bold, in `assets/fonts/`); Helvetica Neue (body); Pinyon Script (slogan only); IranSharp (Persian, not shipped).
- **Feel:** quiet luxury, understated, heritage; crisp 4px corners; golden-hour imagery with fabric/wood/stone texture; cream 30% overlay on busy photos.
- **Logo:** `assets/logo/mark-*.svg` + the `Logo` component. Never redraw the mark.
- **Icons:** Lucide (line, CDN) — a substitution pending a bespoke set.

## Files
- `styles.css` — link this one file to inherit every token + font.
- `readme.md` — full design guide (personas, voice, visual foundations, gradients, iconography, index).
- `components/` — React primitives (Button, IconButton, Input, Badge, Tag, Card, Stat, Tabs, Dialog, Logo, Eyebrow).
- `ui_kits/website/` — full storefront recreation to copy patterns from.
- `guidelines/foundations/` — colour, gradient, type, spacing and brand specimen cards.
