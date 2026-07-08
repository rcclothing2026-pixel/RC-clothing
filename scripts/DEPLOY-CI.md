# Deploy chiiaco.com from anywhere (HTTPS CI) — the "Deploy to chiiaco.com" Action

`.github/workflows/deploy.yml` deploys over **HTTPS**, no VPN dance. A GitHub
runner (outside Iran) builds the release (`composer` + `vite`) and POSTs the zip
to a token-guarded receiver (`public/_deploy_recv.php`) that unzips into
`chiiaco_app`, wires `public_html`, migrates + caches, and busts opcache.

The runner reaches the **site**; the site never contacts GitHub — which is why
this works from an Iran host with no GitHub access.

Trigger it from: **GitHub → Actions → "Deploy to chiiaco.com" → Run workflow**
(tick *migrate* for schema changes), the phone GitHub app, `gh workflow run
deploy.yml`, or ask Claude. The Mac's `scripts/deploy.sh` (FTPS) still works too.

---

## One-time setup

### 1. Server: create the token file (once)
Pick a long random token (e.g. `openssl rand -hex 24`). In cPanel → File Manager,
create **`chiiaco_app/deploy-token.php`** (above the web root, gitignored, never
shipped):

```php
<?php
define('DEPLOY_HTTP_TOKEN', 'PASTE_THE_SAME_RANDOM_TOKEN_HERE');
```

### 2. Server: make sure the receiver is present
`public/_deploy_recv.php` ships with every deploy, so it lands in `public_html`
automatically. **Bootstrap it once** by running one FTP deploy from the Mac
(`bash scripts/deploy.sh`) — or manually upload `public/_deploy_recv.php` to
`public_html/_deploy_recv.php`.

### 3. GitHub: add repo secrets
Settings → Secrets and variables → Actions:

| Secret | Value |
|---|---|
| `PUBLIC_HOST` | `chiiaco.com` |
| `DEPLOY_TOKEN` | the **same** token as in `chiiaco_app/deploy-token.php` |

### 4. Test
Actions → "Deploy to chiiaco.com" → Run workflow → tick **probe_only** first to
confirm HTTPS reachability, then run a normal deploy.

---

## Notes & safety
- **`.env` and `storage/` are never touched** — `.env` is excluded from the zip
  and must already exist at `chiiaco_app/.env`; uploads/logs under `storage/`
  live above the overlay and are left alone.
- **Token** lives only on the server + in the CI secret — never in the repo. A
  wrong/absent token → 403 before any work.
- **Migrations** run only when you tick *migrate*; `migrate --force` is
  idempotent (a no-op when nothing is pending).
- **Upload limit:** the build zip is ~12 MB. If the receiver returns 413, raise
  `upload_max_filesize`/`post_max_size` in `public_html/.user.ini`.
- If the receiver returns anything other than `DONE`, the workflow fails loudly
  with the server's JSON log — nothing is left half-applied silently.
