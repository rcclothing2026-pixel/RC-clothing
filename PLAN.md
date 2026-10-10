# Chiiaco — E-commerce Website Plan

> Persian (Farsi / RTL) clothing store. The brand already sells online and is
> rebranding. New visual identity (CI) is being designed by graphists; this
> build delivers a **fully working store now** with a **theming layer** so the
> new design drops in later without re-architecting.

---

## 1. Goals

- Bilingual-ready but **Persian-first, full RTL** storefront.
- High-impact **landing page** with motion and interactive sections.
- **Shop** (catalog) + **single product** pages with size/variant selection.
- **Cart → Checkout** with **online payment via ZarinPal**.
- **User accounts**: register, login, order history, addresses.
- **Shipping method selection** at checkout (Post, Tipax, express, etc.).
- **Admin panel** to create/edit products, variants, prices, images, stock.
- **Stock availability synced from StoqS** via API (wired later behind an
  adapter so we can build now with internal stock).
- Design-system / theming layer so the **new CI** can be applied later.

---

## 2. Recommended tech stack

| Concern | Choice | Why |
|---|---|---|
| Framework | **Next.js (App Router, TypeScript)** | SSR/SSG for SEO + speed, server actions, one codebase for storefront + admin + API |
| Styling | **Tailwind CSS** + CSS variables (design tokens) | RTL via logical properties; tokens let the new CI swap in cleanly |
| Animation | **Framer Motion** (+ Lenis smooth scroll) | The "motions and interesting interactions" on the landing page |
| Database | **PostgreSQL** + **Prisma ORM** | Reliable relational data for orders/products; typed schema |
| Auth | **Auth.js (NextAuth)** or custom, with **SMS OTP** | OTP login is the norm in Iran (see §7) |
| Payment | **ZarinPal v4 REST** | Iranian gateway, redirect + verify flow |
| State (cart) | **Zustand** (client) + server cart on login | Lightweight, persists to localStorage and DB |
| Fonts | **Vazirmatn** (open-source Persian font) | Clean Farsi typography |
| Admin | Custom dashboard in the same app (role-gated) | Full control over product/variant model + StoqS sync |
| Validation | **Zod** | Shared form + API validation |
| Deploy | **Docker on a VPS** (see §9 hosting note) | Sanctions make western PaaS unreliable for Iran |

**Alternatives considered (open for discussion):**
- **WooCommerce/WordPress** — fastest to stand up, huge Iranian plugin/payment
  ecosystem, but limits the bespoke landing-page motions and custom StoqS sync.
- **Medusa.js (headless) + Next.js storefront** — strong commerce backend out of
  the box, but more moving parts than we need at this stage.

Recommendation: **custom Next.js** for full control over the bespoke design,
animations, and the StoqS integration. Decision is yours.

---

## 3. Architecture

```
Next.js app (one repo)
├─ Storefront (public, RTL, Farsi)
│   ├─ Landing (animated sections)
│   ├─ Shop / category / search & filter
│   ├─ Product detail (variant + size picker)
│   ├─ Cart / Checkout (shipping + ZarinPal)
│   └─ Account (orders, addresses, profile)
├─ Admin (role-gated dashboard)
│   ├─ Products / variants / inventory / images
│   ├─ Orders / fulfillment
│   ├─ Categories, shipping methods, discounts
│   └─ StoqS sync status
├─ API / server actions
│   ├─ Cart, checkout, payment callbacks
│   ├─ Auth (OTP)
│   └─ Integrations: ZarinPal, StoqS adapter, SMS
└─ Theming layer (design tokens / CSS variables)
```

**StoqS adapter pattern:** all stock reads go through one `InventoryProvider`
interface. Today it reads our own DB; when StoqS is ready we implement the same
interface against their API (push webhooks or scheduled pull + cache). Nothing
else in the app changes.

---

## 4. Theming for the upcoming CI

- All colors, spacing, radii, fonts as **CSS variables / Tailwind tokens**.
- Components built against tokens, not hard-coded values.
- A neutral but polished placeholder theme now; new CI = swap the token file +
  asset set. No structural rework needed when the design lands.

---

## 5. Persian / RTL

- `dir="rtl"`, `lang="fa"` globally; logical CSS properties throughout.
- Vazirmatn font; Persian (Jalali) date display where dates are shown.
- **Persian (Jalali) calendar** formatting; **Toman/Rial** currency formatting
  with Persian digits.
- Copy and labels in Farsi from day one; structure ready for English later if
  ever wanted.

---

## 6. Feature breakdown

### Landing page (motion-rich)
- Animated hero, scroll-triggered reveals, featured collections, lookbook,
  category highlights, new arrivals, brand story, newsletter, footer.

### Shop / catalog
- Grid with filters (category, size, color, price), sort, pagination/infinite
  scroll, search. Stock-aware (greys out sold-out sizes).

### Single product page
- Gallery, size & color variant picker, price, stock status, size guide,
  add-to-cart, related products, description tabs.

### Cart & checkout
- Cart drawer + full cart page.
- Checkout: address, **shipping method + cost**, order summary, then ZarinPal.

### Accounts
- OTP register/login, profile, saved addresses, order history + status.

### Shipping
- Admin-defined methods (Post, Tipax, express, free over X) with cost rules
  per method/region.

### Payment — ZarinPal v4 REST
- Create order (`pending`) → request payment (get `authority`) → redirect to
  gateway → callback → **verify** → mark `paid` + decrement stock → confirmation
  + SMS. Idempotent verification; handle failed/cancelled returns.

### Admin
- Product CRUD with variants (size × color), images, pricing, inventory,
  categories, collections, discounts, orders, shipping config, roles.

### Chiaco Stock-Keeping integration (the "StoqS" system)
**System of record:** `rcclothing2026-pixel/chiaco-stock-keeping` — a multi-tenant
**POS + inventory SaaS** (PHP). The website is a **client** of this app, not the
master of commerce data. It must:
- **Read** live stock / availability per product+size from stock-keeping.
- **Report** every **sale / order**, **income / payment**, and **customer (CRM)**
  back to stock-keeping so all reporting lives in one place.

Design: a single `StockKeeping` client (API adapter) wraps all calls. The website
keeps a light local mirror/cache for fast page loads, but stock-keeping is
authoritative. Reporting is event-driven (on order paid → push sale + income +
customer) with a retry queue so the storefront never blocks on the SaaS.

> **Blocked / needs input:** the stock-keeping repo is private and not in this
> session's access scope, so its exact API/data model is unknown. The website is
> being built behind a clean adapter so the contract can be wired once available.
> See open question #5.

---

## 7. Authentication note (Iran)

In Iran, **phone-number + SMS OTP** is the expected login method (low reliance
on email). Plan: OTP via an Iranian SMS provider (e.g., **Kavenegar / SMS.ir**),
optional email/password as secondary. Confirm your preference in the questions.

---

## 8. Data model (first pass)

`User`, `Address`, `Category`, `Collection`, `Product`, `ProductVariant`
(size, color, sku, price, stock), `ProductImage`, `Cart`, `CartItem`,
`Order`, `OrderItem`, `ShippingMethod`, `Payment`, `Discount`, `Review` (opt).

---

## 9. Roadmap (phased)

- **Phase 0 — Foundation:** Next.js + Tailwind + RTL + Vazirmatn + tokens, DB +
  Prisma schema, base layout/nav/footer.
- **Phase 1 — Catalog:** product/variant model, shop + filters, product page
  (internal stock).
- **Phase 2 — Cart & accounts:** cart, OTP auth, addresses.
- **Phase 3 — Checkout & payment:** shipping methods, ZarinPal request/verify,
  order confirmation + SMS.
- **Phase 4 — Admin:** product/order/inventory/shipping management.
- **Phase 5 — Landing polish:** full motion design.
- **Phase 6 — StoqS:** swap inventory provider to live API.
- **Phase 7 — New CI:** apply final design tokens/assets.

**Hosting note:** US/EU PaaS (e.g., Vercel) can be unreliable for Iranian
traffic and gateways due to sanctions. Recommend **Docker on an Iranian/region-
appropriate VPS** with Postgres. To confirm.

---

## 10. Open questions (for you)

1. Build approach: **custom Next.js** (recommended) vs WooCommerce vs Medusa?
2. Login: **SMS OTP** (recommended) / email+password / both?
3. Currency display: **Toman** vs Rial?
4. First deliverable: scaffold the storefront with **placeholder/mock products
   now**, or wait for more inputs?
5. Do you have StoqS API docs yet, or build behind the adapter for now?
6. Shipping providers to support at launch (Post / Tipax / express / pickup)?

_This document is a living plan — tell me what to add or remove._
