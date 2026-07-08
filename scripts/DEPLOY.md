# Deploying Chiaco to cPanel (chiiaco.com) — FTP, no SSH

One command: **`bash scripts/deploy.sh`**. It builds a release zip, uploads it
over FTP, and a token-guarded one-shot PHP script unzips it server-side, runs
migrations + caches, busts opcache, and deletes itself.

```
git pull            # VPN ON  (GitHub is blocked from Iran)
# turn VPN OFF      (FTP/HTTPS to the Iranian host need VPN off)
bash scripts/deploy.sh
```

Layout on the server (app kept above the web root — secure):

```
/home/chiiac/
├── chiiaco_app/        ← the Laravel app (code, vendor, storage, .env)
│   └── .env            ← you upload this ONCE (never touched by deploy)
└── public_html/        ← web root for chiiaco.com (only public/ lives here)
    ├── index.php       ← rewritten by the deployer to boot ../chiiaco_app
    ├── build/ …        ← compiled assets
    └── storage → ../chiiaco_app/storage/app/public   (symlink)
```

---

## One-time setup (first deploy only)

Do these once in cPanel, then run `deploy.sh`.

### 1. PHP version
cPanel → **Select PHP Version** → set **8.3** (or newer) for `chiiaco.com`.
Enable extensions: `pdo_mysql`, `mbstring`, `openssl`, `zip`, `fileinfo`, `gd`, `bcmath`, `intl`.

### 2. Database
cPanel → **مدیریت دیتابیس‌ها / MySQL Databases**:
- Create a database (e.g. `chiiac_db`) and a user, give the user **all privileges** on it.
- Note the final names (cPanel prefixes them, e.g. `chiiac_chiiac_db`).

### 3. Credentials for the deploy script
```bash
cp scripts/.chiiaco-deploy.example ~/.chiiaco-deploy
chmod 600 ~/.chiiaco-deploy
# edit ~/.chiiaco-deploy → set FTP_PASS (host/user/dirs are pre-filled)
```

### 4. Production `.env` (uploaded once over FTP)
```bash
cp .env.production.example .env.production
php artisan key:generate --show     # paste the result into APP_KEY=
# fill DB_* (from step 2), APP_URL, MAIL_*, etc.
```
Then upload it via FTP to **`/home/chiiac/chiiaco_app/.env`** (create the
`chiiaco_app` folder if needed). FileZilla or:
```bash
curl --ssl-reqd -k -T .env.production -u 'chiiac@chiiaco.com' \
  'ftp://764087185.cloudylink.com/chiiaco_app/.env'
```
The deployer **never** creates or overwrites `.env` — your secrets stay put.

### 5. Cron (cPanel → Cron Job) — the scheduler + queue worker
Laravel needs one cron for the scheduler, and (since the queue is DB-backed) one
to drain queued jobs (StoqS sale reports, emails). Add both (every minute):

```
* * * * * /usr/local/bin/php /home/chiiac/chiiaco_app/artisan schedule:run >> /dev/null 2>&1
* * * * * /usr/local/bin/php /home/chiiac/chiiaco_app/artisan queue:work --stop-when-empty --max-time=55 >> /dev/null 2>&1
```
(Confirm the PHP CLI path in cPanel; it may be `/usr/local/bin/ea-php83` or similar.)

---

## Every deploy after that

```
git pull            # VPN ON
# VPN OFF
bash scripts/deploy.sh
```

Useful flags:
- `--no-migrate` — ship files/assets only (no DB changes)
- `--no-build`   — reuse the last `RELEASE/chiiaco-deploy.zip`
- `--no-verify`  — skip the post-deploy HTTP check
- `--help`

What it does each run: `npm run build` → stage + `composer install --no-dev` →
zip → FTP upload → upload+trigger the one-shot extractor (unzip, `migrate --force`,
`config:cache`, `view:cache`, `storage:link`, opcache reset) → it deletes the zip
and itself → verifies `https://chiiaco.com` and `/up`.

---

## Notes & safety

- **Data is never overwritten.** Deploy runs *migrations* (schema), not seeds.
  `storage/` (uploads, logs) is excluded from the zip and left untouched on the
  server. Local test/smoke data never ships (`SmokeSeeder` is not auto-run).
- **The extractor is single-use.** A fresh random token per deploy, and it
  removes itself after running — it is not left reachable on the site.
- **Stale files:** the zip overlays files; it won't delete files you removed in
  a later release. If you ever delete a file from the app, remove it on the
  server too (rare).
- **First deploy can't migrate without `.env`** — if you see a "missing .env"
  message, finish step 4, then re-run.
- **VPN dance:** GitHub needs VPN **on**; the Iranian host (FTP + HTTPS) needs it
  **off**. Pull first (VPN on), switch it off, then deploy.
- **FTPS vs FTP:** the script uses explicit FTPS by default. If uploads fail with
  a TLS error, set `FTP_SECURE=0` in `~/.chiiaco-deploy` (plain FTP).
