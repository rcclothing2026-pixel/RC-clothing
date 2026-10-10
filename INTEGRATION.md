# Website ↔ Chiaco Stock-Keeping integration

This document is the **contract checklist** for connecting this website to the
`rcclothing2026-pixel/chiaco-stock-keeping` app (multi-tenant POS + inventory).
In a session that has **both repos**, use this to map the website's expectations
onto the stock-keeping app's real API and adjust either side as needed.

## Roles
- **chiaco-stock-keeping** = system of record for **stock, sales, income, CRM**.
- **website** = owns the **catalog** (products/prices/images), and **reports**
  every sale / income / customer to stock-keeping; **reads** live stock from it.

## The single integration seam (website side)
All calls go through one interface — wire the real API here, nothing else changes:

| Concern | File |
| --- | --- |
| Contract (methods the site needs) | `app/Services/StockKeeping/StockKeepingClient.php` |
| No-op (used when disabled) | `app/Services/StockKeeping/NullStockKeepingClient.php` |
| **Real HTTP client (fill in TODOs)** | `app/Services/StockKeeping/HttpStockKeepingClient.php` |
| Binding / on-off switch | `app/Providers/AppServiceProvider.php`, `config/stockkeeping.php` |
| Reliable outbox (retries) | `integration_events` table, `App\Models\IntegrationEvent` |
| Catalog→POS mapping fields | `products.stockkeeping_id`, `product_variants.stockkeeping_variant_id` |

Enable with `STOCKKEEPING_ENABLED=true` + `STOCKKEEPING_BASE_URL` /
`STOCKKEEPING_API_KEY` / `STOCKKEEPING_TENANT_ID` (see `.env.example`).

## Assumed contract (TO CONFIRM against the real API)
These are placeholders in `HttpStockKeepingClient`; replace paths/payloads to match.

1. **Read stock** — `GET /api/v1/stock?variant_ids=...`
   → `{ data: [ { variant_id, available } ] }`
2. **Report sale** — `POST /api/v1/sales` (order id, items[variant_id, qty, unit_price], totals, customer ref) → `{ id }`
3. **Report income** — `POST /api/v1/incomes` (sale ref, amount, gateway=ZarinPal, ref_id, paid_at) → `{ id }`
4. **Upsert customer (CRM)** — `POST /api/v1/customers` (phone, name, addresses…) → `{ id }`

Auth assumed: `Authorization: Bearer <api key>` + `X-Tenant-Id: <tenant>`.

## Questions for the stock-keeping side
- Multi-tenant: how is this store's **tenant** identified (header / subdomain / key)?
- Are products **created in the POS** or pushed from the website? (We assumed
  website-owned catalog → needs a "create/sync product" endpoint, TBD.)
- Stock updates: **pull** on demand, scheduled sync, or **webhook** push to the site?
- Idempotency keys for sale/income so retries don't double-count?
- Currency unit on the POS side (Toman vs Rial) for amounts.

## Open items to build once confirmed
- [ ] Implement the 4 `HttpStockKeepingClient` methods against real endpoints.
- [ ] Outbox worker (queued job) to drain `integration_events` with backoff.
- [ ] Product/variant ↔ stock-keeping id mapping on create/sync.
- [ ] Stock read path on product/shop pages (cache + fallback to local).
