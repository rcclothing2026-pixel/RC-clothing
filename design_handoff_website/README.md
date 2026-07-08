# Handoff: Racket Club — Editorial Website (build brief)

## Overview
This package is the **Racket Club design system** plus a brief to build the brand's
**artistic, editorial website**. Racket Club is a quiet-luxury **leisurewear** brand — "The Art
of Leisure," the life *off* the court. Slogan: **Legends & Legacy**. Archetype: The Sovereign.

The goal for the developer: stand up the Racket Club marketing/editorial website in a real
codebase, driven by this design system and by the brand book as the art-direction reference.

## About the design files
The files in this bundle are a **design system + HTML design references**, not production code to
ship as-is:
- `styles.css` + `tokens/` — the real, portable **CSS custom properties** (colours, type, spacing,
  radius, motion) and `@font-face`. These you can use directly.
- `components/` — React **primitives** written as references (`.jsx` + `.d.ts` + `.prompt.md`).
  Treat the `.d.ts`/`.prompt.md` as the API contract; re-implement in the target stack's idioms.
- `brand-book/index.html` (scroll) and `brand-book/index-a4.html` (paged) — the **art direction**
  for the whole brand: layout rhythm, type treatment, colour balance, imagery. Use as the visual
  north star for the website.
- `ui_kits/website/` — an earlier storefront prototype (header, hero, product grid, cart) you can
  mine for component composition patterns.

Recreate these in the codebase's environment. **Recommended stack if none exists: Next.js + React**
(the components are already React). Port `styles.css` as global CSS (or map tokens into Tailwind
theme / CSS-in-JS). Keep the tokens as the single source of truth.

## Fidelity
**High-fidelity.** Colours, typography, spacing, radii and motion are final and exact — build
pixel-accurately from the tokens and the brand book.

## The website to build (brief)
An immersive editorial site that feels like the brand book in motion. Suggested sections:
1. **Hero** — full-bleed campaign image or solid navy ground; Mansory H1 (UPPERCASE), Pinyon
   slogan, one clay CTA.
2. **The Art of Leisure** — editorial manifesto, generous whitespace, a single strong image.
3. **The Collection / first drop** — product-led grid (polos, crew, shorts, socks, tote); hover
   reveals; leads to product/shop.
4. **Lookbook / editorial** — full-bleed photography with restrained captions.
5. **The Club** — brand story, personas/community, ambassadors.
6. **Footer** — nav, newsletter, slogan.

**Open decisions to confirm with the client before building:** single immersive scroll vs
multi-page; motion intensity (subtle fades/parallax vs bold scroll-driven reveals); purely
editorial vs commerce-enabled (the storefront kit is a starting point if commerce).

## Voice & content
Refined, confident, evocative — **sell the moment, not the product** ("Style for your leisure
moments," not "Buy our t-shirt"). H1 UPPERCASE, H2/H3 Title Case, body sentence case. No emoji.
Slogan (Pinyon Script) at most once per view.

## Design tokens
Authoritative source: `styles.css` → `tokens/*.css`. Core values:
- **Colours:** Navy `#18234F`, Forest Green `#034326`, Clay `#A72F23`, Brick Brown `#672C24`,
  Warm Beige `#C6AF92`, Cream `#ECE7D0` (page), White. Studio grounds: Terracotta `#A8492C`,
  Petrol `#1E3D46`. Each core colour has a 10-step ramp (`--navy-50…900`, etc.).
- **Balance:** 60% cream surface / 30% navy structure / 10% accents.
- **Gradients:** two are defined in tokens (`--gradient-primary` navy→forest, `--gradient-accent`
  clay→brown). NOTE: the client currently prefers **solid colour** in the brand book — confirm
  whether the website should use the gradients or stay solid.
- **Type:** Mansory (headings/logo — files in `assets/fonts/`, supplied by client), Helvetica Neue
  (body/UI), Pinyon Script (slogan, Google Font). Scale: H1 48 / H2 36 / H3 24 / body 16 / caption 12.
- **Radius:** crisp — default `--radius-sm` 4px; pills for tags only.
- **Motion:** `--ease-out`, 120–360ms, no bounce.

## Components (contracts in `components/*/`)
Core: `Button` (variants primary/secondary/accent[gradient]/gradient/outline/ghost),
`IconButton`, `Input`, `Badge`, `Tag`, `Card` (default/sunken/inverse/court), `Stat`, `Tabs`,
`Dialog`. Brand: `Logo` (mark/wordmark/lockups, tones navy/cream/green), `Eyebrow`.
Each has a `.d.ts` (props) and `.prompt.md` (usage) — read those for the exact API.

## Assets
- `assets/logo/mark-navy.svg · mark-cream.svg · mark-green.svg` — the mark (vector, exact CI geometry).
- `assets/fonts/Mansory*.ttf` — brand typeface (client-licensed; confirm web licensing).
- `assets/photography/*.jpg` — the campaign shoot (nine images). NOTE: some filenames don't match
  their contents (a labelling quirk) — verify each image visually before wiring it to a slot.
- Icons: **Lucide** (`lucide@0.454.0`, CDN) — a substitution; swap for a bespoke set if provided.

## Using this as an Agent Skill in Claude Code
This bundle is Agent-Skills compatible. Drop the folder into your repo (e.g.
`.claude/skills/racket-club-design/`) — `SKILL.md` at the root is the entry point. Then ask Claude
Code to "use the racket-club-design skill" and build the site; it will read `readme.md`, the tokens,
the component contracts, and the brand book.

## Files
- `SKILL.md`, `readme.md` — skill entry + full design guide.
- `styles.css`, `tokens/` — tokens + fonts.
- `components/` — component references + contracts.
- `brand-book/index.html`, `brand-book/index-a4.html` — art-direction reference.
- `ui_kits/website/` — storefront composition reference.
- `guidelines/foundations/` — foundation specimen cards.
- `assets/` — logo, fonts, photography.
