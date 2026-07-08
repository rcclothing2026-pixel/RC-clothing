# Racket Club — Design System

A corporate-identity design system for **Racket Club** — a quiet-luxury **leisurewear / clothing
brand**. Racket Club is *not* a sports club; it dresses the life that happens **off** the court.

> **Essence — "The Art of Leisure."** Racket Club is more than a clothing label; it is the
> embodiment of a lifestyle. It lives in the moments away from the tennis court and celebrates the
> culture, camaraderie and endless elegance of racket sports. It is not about the sweat — it is about
> the style; not about competition — about community. **Slogan: _Legends & Legacy_.**

---

## Sources (client-supplied)
All originals live in `uploads/`:
- `RACKET CLUB IDENTITY.docx` / `RACKET CLUB info.pdf` — the brand identity guide (essence, archetype,
  customer avatars, voice, competitive set, ambassadors, visual-identity map, logo system, typography
  and colour guidelines). Written in Persian + English.
- `LOGO DESIGN RACKET COURT CI-01…06.svg` + `CI2.pdf` — the CI board: mark, wordmark, lockup, palette, type.
- `Mansory *.ttf / *.otf` — the brand typeface (client-supplied). Pinyon Script confirmed for the slogan.

Colour hexes and gradient specs are taken from the identity guide's "visual identity map" and
"colour palette guide" sections. The Farsi body font (**IranSharp**) is named in the guide but was not
supplied — see caveats.

---

## Brand personality
- **Archetype — The Sovereign.** Leadership, control, order from chaos — but for Racket Club this means
  mastery over one's *own* life and leisure, not ruling others. It defines its exclusive "club" by
  taste, quality and a shared appreciation of the finer things; it values heritage and tradition and
  presents a confident, classic, controlled image.
- **Customer — The Modern Classicist**, 20–45, discerning and quality-conscious, wealthy but never showy.
  - *Sara* (F, 20–35) — creative director in a coastal city; weekend padel then a long brunch;
    galleries, indie cinema, art markets; buys timeless, sustainably/limited-made pieces.
  - *Arin* (M, 30–40) — investment advisor / tech founder; evening tennis under lights; collects vintage
    rackets; seeks understated status ("no loud logos") and lasting quality.
- **Competitive set (and the lesson):** Loro Piana (proprietary lux fabric), Ralph Lauren Polo (heritage
  storytelling), Sunspel (fit + limited runs), Mackintosh (craft = premium), Sporty & Rich (tennis-led
  drops).
- **Ideal ambassadors:** Emma Raducanu (modern minimalist), Carlos Alcaraz (dynamic innovator),
  Jannik Sinner (understated master).

---

## Content fundamentals — how Racket Club writes
The voice is **refined, confident, evocative.**
- **Refined:** elegant, measured language. The Mansory typography carries this — chic and strong without
  being aggressive.
- **Confident:** the calm authority of an expert. No shouting; product quality speaks for itself.
- **Evocative:** language paints a picture. We don't say "a good sweatshirt" — we say "the perfect
  crew-neck for a cool evening by the sea." **We sell the moment, not just the product.**
- **Examples (from the guide):**
  - ✗ "Buy our new t-shirt." → ✓ "Style for your leisure moments."
  - ✗ "High-quality shorts." → ✓ "Designed for the life that continues after the match."
- **Casing:** H1 is UPPERCASE; H2/H3 Title Case; body sentence case. Eyebrows/labels are uppercase, tracked.
- **No emoji.** No loud, exclamatory marketing. Punctuation is calm; the slogan (Pinyon Script) appears
  once per view at most.

---

## Visual foundations

- **Palette (CI visual-identity map):**
  - Primary 1 — **Navy** `#18234F` · Primary 2 — **Deep Forest Green** `#034326`
  - Secondary 1 — **Clay/Cherry Red** `#A72F23` · Secondary 2 — **Brick Brown** `#672C24`
  - Accent — **Warm Beige** `#C6AF92` · Background — **Light Cream** `#ECE7D0` · **White** `#FFFFFF`
  - Each has a 10-step ramp (`--navy-50 … --navy-900`, etc.).
- **60-30-10 balance:** 60% cream surface · 30% navy text/structure · 10% accents (clay + beige ≈7.5% each;
  forest + brown ≈2.5% each for details).
- **Signature gradients** (the brand *does* use gradients, only these two):
  - **Primary** — navy → forest, top-right to bottom-left (`--gradient-primary`, 225°). Website headers,
    hero, catalogue covers.
  - **Accent** — clay → brown, top to bottom (`--gradient-accent`, 180°). CTA buttons, ribbons, banners.
- **Type:** **Mansory** for headings, logo and pull-quotes (H1 Bold 48px UPPERCASE, H2 SemiBold 36px,
  H3 Medium 24px). **Helvetica Neue** for body / UI (16px regular, captions 12px). **Pinyon Script** for
  the slogan only. Persian uses **IranSharp** (not shipped). Line-height: headings 1.2, body 1.4, caption 1.3.
  Bold only for key CTAs/words; keep to Regular/Medium, minimal italics. Headings in navy `#18234F` or
  clay `#A72F23`; body in forest `#034326` or navy on cream.
- **Imagery:** soft natural "golden-hour" light; coastal portraits mixed with tennis-court interiors;
  textures foregrounded — terry & piqué fabrics, aged wood, stone walls. Apply a **cream overlay
  (`#ECE7D0` at ~30%)** over busy background images to keep text legible. Scenes: coastal boardwalk,
  vineyard terrace, sunset courts.
- **Motifs & patterns:** fine herringbone, tonal (same-colour) logo, thin outline rules.
- **Corners:** crisp — the mark is a hard square; default radius `--radius-sm` (4px); pills for tags/status only.
- **Cards:** cream/white surface, hairline border, soft navy-tinted `--shadow-sm`; lift on hover for interactive.
- **Motion:** quiet, `--ease-out`, no bounce, 120–360ms.
- **Hover / press:** solid buttons darken a step and rise 1px; gradient buttons shift brightness slightly;
  links are clay `#A72F23`, hovering to warm beige `#C6AF92`; focus shows a translucent clay ring.

---

## Iconography
The CI shipped **no icon set**. For UI glyphs the system uses **Lucide** (CDN, `lucide@0.454.0`) — a thin
(~1.6px stroke), geometric line set that suits the brand's crisp, understated geometry. This is a
documented **substitution**, flagged for sign-off. Icons are line style, 16–20px, `currentColor`, no fills.
No emoji, no unicode-glyph icons. The **brand mark** (`assets/logo/`) is the logo, used as favicon /
watermark / lockup element — not as a UI icon.

---

## Assets
- `assets/logo/mark-navy.svg` · `mark-cream.svg` · `mark-green.svg` — the mark on each ground (exact CI geometry).
- `assets/fonts/Mansory*.ttf` — brand typeface (regular, medium, semibold, bold, oblique).
- Wordmark + lockups render live via the `Logo` component (Mansory webfont). Helvetica Neue is a system font.

---

## Components
Reusable React primitives (`components/`), exported on `window.RacketClubDesignSystem_3c2e87`.

**Core** (`components/core/`): `Button`, `IconButton`, `Input`, `Badge`, `Tag`, `Card`, `Stat`, `Tabs`, `Dialog`.
**Brand** (`components/brand/`): `Logo`, `Eyebrow`.

### Intentional additions
The CI defined identity only (no component inventory), so this is a standard commerce-sized set.
`Button` carries the two signature gradients (`accent`, `gradient` variants); `Logo` renders the mark +
Mansory wordmark from tokens; `Stat`/`Eyebrow` support the editorial marketing style.

---

## UI kits
- **`ui_kits/website/`** — the Racket Club **storefront**: promo bar, sticky header, primary-gradient hero
  with lookbook slot, "Essentials" product grid with add-to-bag, editorial band, collections, footer, and a
  slide-in cart drawer. All photography is `image-slot` placeholders for real imagery. `index.html` is the entry.

---

## Foundation cards
`guidelines/foundations/` — Design System tab specimens: colour ramps + semantic roles + the six-colour
palette, the two signature gradients, Mansory display / Helvetica Neue body / weights / Pinyon slogan,
spacing / radius / elevation, and mark + lockup usage.

---

## Index (root manifest)
- `styles.css` — global entry (link this one file). Imports the tokens below.
- `tokens/fonts.css` — `@font-face` (Mansory) + Pinyon import.
- `tokens/colors.css` — ramps, semantic aliases, signature gradients, 60-30-10 note.
- `tokens/typography.css` — Mansory headings / Helvetica Neue body, scale + heading roles.
- `tokens/spacing.css` — 4px spacing scale + layout widths.
- `tokens/effects.css` — radius, elevation, motion.
- `components/core/`, `components/brand/` — React primitives (`.jsx` + `.d.ts` + `.prompt.md` + card).
- `guidelines/foundations/` — specimen cards.
- `ui_kits/website/` — storefront UI kit.
- `brand-book/index.html` — the full editorial brand book (this deliverable).
- `assets/` — logo marks, fonts + `photography/` (campaign shoot).
- `SKILL.md` — Agent-Skill wrapper.

---

## Caveats / open questions
- **Slogan wording varies across the source files** — the identity docs say *"Legends & Legacy"* (used
  here); the CI board shows *"Legend & Legacy"* and the PDF once reads *"Legacy & Legends."* Please confirm the canonical form.
- **The wordmark reads "ROCKET CLUB" in one PDF note** but "RACKET CLUB" on the CI art — treated as a typo;
  using **RACKET CLUB**. Confirm.
- **Icons are Lucide (substitution)** — pending a bespoke set.
- **IranSharp (Persian body font) not supplied** — declared in the guide; add the files to enable Farsi.
- **No photography supplied** — the storefront uses `image-slot` placeholders; drop in real imagery.
- The Farsi guideline text was interpreted for colour *roles* and gradients — worth a native-speaker check.
