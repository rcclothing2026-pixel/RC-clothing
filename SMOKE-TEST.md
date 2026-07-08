# Chiaco — Smoke Test Checklist

Click through these on your local site (`http://localhost:8002`). Each item lists
**what to do** and **what you should see**. Tick the box once verified. Anything
that 500s, shows a console error, or behaves wrong → tell me and I'll fix it.

> Tip: open DevTools (⌥⌘I) → **Console** + **Network** tabs while testing, so you
> catch silent failures (red console lines, 4xx/5xx requests).

Routes verified to return 200 in automated smoke (storefront + admin GETs all pass).
The interactive bits below are what need a human.

---

## 0. Setup
- [ ] `git pull origin claude/vibrant-einstein-uficlc && npm run build`
- [ ] Log in: visit `/dev/login-admin` (instant admin) **or** `/login` (real OTP flow — see §6)

## 1. Header / Footer / Nav (every page)
- [ ] Logo (چیاکو + Gate sign) renders crisp in the **header** and **footer**
- [ ] Footer oversized **CHIAC⊙** band — "O" shows as the ⊙ bullseye, reads left-to-right
- [ ] Nav categories dropdown opens; links go to the right `/shop?category=…`
- [ ] Cart icon + account icon work; mobile (≤768px) hamburger menu opens/closes
- [ ] Search box: type 2+ chars → suggestions dropdown appears, clicking one opens the product

## 2. Home (`/`)
- [ ] Hero: tagline lockup, title, both CTA buttons work; tagline **badge** stamp on the image
- [ ] Gate pattern faintly visible in hero background
- [ ] Categories / Featured / New arrivals grids render; product cards clickable
- [ ] Promo marquee scrolls and shows "Change in Aesthetics" with ⊙
- [ ] Lookbook 3-card section; each card links to shop
- [ ] Section kickers show the ⊙ dot; scroll-reveal animations fire once

## 3. Shop (`/shop`)
- [ ] Product grid loads; pagination works (page 2 keeps filters)
- [ ] **Sort** dropdown (newest / price asc / desc) reorders + shows skeleton flash
- [ ] **Filter** (category, size, price min/max) applies; active-filter chips appear; "remove all" clears
- [ ] Mobile: filter button opens the **bottom-sheet** drawer; drag handle + backdrop close it
- [ ] Collection banner: open `/shop?collection=<slug>` → dark hero with pattern + red cut + title
- [ ] Category banner: `/shop?category=manto` → category hero shows
- [ ] List/grid view toggle (if present) works

## 4. Product detail (`/product/<slug>`)
- [ ] Gallery thumbnails switch the main image
- [ ] Color + size selectors (⊙ labels) update; out-of-stock variant disables add-to-cart
- [ ] Quantity stepper +/- works; **Add to cart** adds and shows confirmation
- [ ] **Sticky add-to-cart bar** (mobile, ≤1024px): scroll down past the form → bar slides up from bottom; its button adds the selected variant
- [ ] Wishlist heart toggles (filled/unfilled)
- [ ] Share: WhatsApp link + "copy link" (shows "کپی شد")
- [ ] Stock-notify form appears for out-of-stock variant; submitting shows success
- [ ] Reviews: list renders; logged-in can submit a rating; related + recently-viewed show

## 5. Cart (`/cart`)  ⚠️ *bugs fixed here — verify*
- [ ] Cart with items renders (no 500) — **was 500ing on a stale compiled view**
- [ ] +/- stepper: **line total updates instantly** (optimistic), then page reloads with server total
- [ ] Quantity typing (inputmode numeric) works on mobile keyboard
- [ ] **Coupon apply** — enter a code → shows success/error message inline (**was always erroring before; Accept header fixed**)
- [ ] Gift card apply (separate form) works
- [ ] Remove item; empty-cart state shows "منتخب چیاکو" recommendations
- [ ] Free-shipping progress text updates with subtotal

## 6. Auth — OTP login (`/login`)  ⚠️ *3 bugs fixed — verify carefully*
- [ ] Enter phone `09120000000` → redirects to verify page
- [ ] Verify page shows **exactly 5 boxes** (was 6 — mismatch with 5-digit code)
- [ ] Dev code banner shows the code; typing **auto-advances** to the next box (was broken)
- [ ] Backspace on empty box jumps to previous box
- [ ] Paste the 5-digit code → auto-fills + auto-submits
- [ ] **ورود** button submits and logs you in (was failing silently)
- [ ] New account → redirected to `/complete-profile` (name + surname), then to account
- [ ] Resend code link appears after countdown and works

## 7. Account (auth)
- [ ] `/account` — profile loads; edit profile (PATCH) saves
- [ ] `/account/orders` — order list renders
- [ ] `/account/orders/<number>` — **order status timeline** renders correctly (checkmarks for done steps, filled dot for current, hollow for future; progress line anchored right→left). Verified across all 6 statuses.
- [ ] Order invoice opens (`/…/invoice`)
- [ ] Return request form opens + submits (`/…/return`)
- [ ] Add / delete a shipping address
- [ ] `/wishlist` — items show; toggle/remove works

## 8. Checkout (auth, cart non-empty)
- [ ] `/checkout` renders (redirects to `/cart` if empty — expected)
- [ ] Province select → **city dropdown populates** (AJAX `/checkout/cities/…`)
- [ ] Phone / postal-code inputs use numeric keypad on mobile
- [ ] Shipping method selection updates totals
- [ ] Smart discount-rule lines + redeem-points show correctly
- [ ] Place order → reaches payment gateway / success or failed page
      *(Test with a real flow; this touches payment — do a careful pass.)*

## 9. Brand reference (`/brand`)
- [ ] Color palette + 4 gradients render
- [ ] Logo: real چیاکو/CHIACO in dark-on-white, white-on-dark, on-red (all crisp?)
- [ ] Patterns: **Exact swatch** vs **Seamless tile** vs **Solid fill** — pick which looks best
- [ ] Tagline: inline + real circular badge (dark / light / red)
- [ ] Display type: ONLINE SHOPPING / END OF SEASON OFF / CHIACO — ⊙ for O, **left-to-right**

## 10. Misc
- [ ] Contact form (`/contact`) submits
- [ ] Newsletter signup (footer/block) submits; no console error
- [ ] Cookie-consent bar appears once, dismiss persists
- [ ] Content pages (`/page/about`, `/page/faq`, etc.) render
- [ ] 404 page (visit a bad URL) shows branded error

## 11. Admin (quick pass — `/admin`)
- [ ] Dashboard, Products, Orders, Discount-rules, Media, Settings pages all load
- [ ] Create/edit a product; upload an image (media library)
- [ ] Order detail → change status; customer sees updated timeline

---

### Bugs already found & fixed in this pass
1. **OTP verify** — form had 6 boxes but codes are 5 digits (`config('sms.otp_length')`); now config-driven.
2. **OTP verify** — Alpine dynamic `:ref` unsupported → focus didn't advance + submit failed silently; rewritten with DOM traversal + native submit.
3. **Cart** — 500 from a stale compiled Blade view; hardened the `@php` block to the parenthesized form (file convention). *Lesson: always `view:clear`/`view:cache` on deploy.*
4. **Cart coupon** — `fetch()` lacked `Accept: application/json`, so the server returned a redirect instead of JSON and the coupon box always showed a connection error; header added.

All four are in `verify.blade.php` + `cart/index.blade.php` — ship via `deploy.sh` (Blade views, no migration).
