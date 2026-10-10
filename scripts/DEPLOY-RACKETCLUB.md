# Deploying Racket Club to cPanel (racketclub.ir)

This is a **Laravel (PHP 8.3) app** — not a WordPress/Sitejet site. It needs a
MySQL database and the web root pointed at Laravel's `public/`. Host facts from
the account: cPanel user `chiiac`, home `/home/chiiac`, primary domain
`racketclub.ir`, MySQL available, SSL currently self-signed.

Recommended server layout (app kept ABOVE the web root — the secure pattern):

```
/home/chiiac/
├── racketclub_app/     ← the Laravel app (code, vendor, storage, .env)
│   └── .env            ← upload ONCE (never overwritten by deploys)
└── public_html/        ← web root for racketclub.ir (only public/ lives here)
    ├── index.php       ← boots ../racketclub_app
    ├── build/ …        ← compiled Vite assets
    └── storage → ../racketclub_app/storage/app/public   (symlink)
```

## One-time setup (in cPanel)

1. **PHP version** — *Select PHP Version* → **8.3**. Enable extensions:
   `pdo_mysql, mbstring, openssl, zip, fileinfo, gd, bcmath, intl, curl`.
2. **Real SSL** — *SSL/TLS Status* → run **AutoSSL** (Let's Encrypt) for
   `racketclub.ir` + `www`. The self-signed cert must be replaced before taking
   payments / OTP logins (browsers block, ZarinPal callbacks need valid HTTPS).
3. **Database** — *MySQL Databases*: create a DB + user (e.g. `chiiac_racketclub`
   / `chiiac_rc`), grant the user **ALL PRIVILEGES**. Note the prefixed names.
4. **.env** — copy `.env.production.example` → fill real values (APP_KEY via
   `php artisan key:generate --show` locally; DB creds; mail; ZarinPal; SMS) →
   upload as `/home/chiiac/racketclub_app/.env`. Never commit it.
5. **Document root** — either point racketclub.ir's doc root at
   `/home/chiiac/racketclub_app/public`, OR keep `public_html` and let the
   deployer wire `public_html/index.php` to boot `../racketclub_app`.

## First deploy

Shared cPanel usually has **no SSH/composer/npm**, so build locally and upload:

```
# locally, from the repo root:
composer install --no-dev --optimize-autoloader
npm ci && npm run build
# zip app WITHOUT node_modules/.git/.env, WITH vendor/ and public/build/
```

Upload + extract into `racketclub_app/`, then run migrations once. Two ways:
- **If cPanel Terminal/SSH is available:** `php artisan migrate --force --seed`,
  `php artisan storage:link`, `php artisan config:cache route:cache view:cache`.
- **If FTP-only:** use the bundled token-guarded deployer (`scripts/deploy.sh`
  + `scripts/server/deploy.php`) — it unzips, wires `public_html`, migrates, and
  caches over HTTPS. Retarget it to racketclub.ir (see below).

## Ongoing deploys — one command, `scripts/deploy.sh`

The deployer is already retargeted to racketclub.ir. Set up credentials once on
your Mac, then every future update (code, migrations, DB/schema changes, cache
rebuilds) ships with a single command.

**One-time (local):**
```
cp scripts/.racketclub-deploy.example ~/.racketclub-deploy
chmod 600 ~/.racketclub-deploy
# edit ~/.racketclub-deploy → fill FTP_PASS (and FTP_HOST/PUBLIC_HOST if different)
```

**Every deploy (local):**
```
# VPN ON:  git pull            (GitHub is blocked in Iran)
# VPN OFF: bash scripts/deploy.sh          # build + upload + migrate + cache
#          bash scripts/deploy.sh --seed   # first deploy only: also seed shipping/payments/pages/menus
```
It builds a release zip (`composer --no-dev` + `vite build`), uploads it over
FTP, uploads a token-guarded one-shot `deploy.php` to `public_html`, triggers it
once over HTTPS (unzip → wire `public_html` → `migrate --force` → `config:cache`
+ `view:cache` → opcache reset), then the server script deletes the zip + itself
and the command verifies the site responds. Flags: `--no-migrate` (assets only),
`--no-build` (reuse last zip), `--pull` (git pull first, if GitHub is reachable).

If `racketclub.ir` DNS isn't pointed at this server yet, add
`TRIGGER_IP=45.139.11.145` to `~/.racketclub-deploy` for the first deploys.

## Queue worker (for order emails / SMS retries)

cPanel → *Cron Jobs*, every minute:
`cd /home/chiiac/racketclub_app && php artisan queue:work --stop-when-empty >/dev/null 2>&1`

## After first deploy — verify
`/` (Persian/RTL home), `/shop`, a product, add-to-bag → `/cart` → `/checkout`,
`/login` (OTP), `/admin`. Toggle EN/فا in the header. Confirm HTTPS is the real
AutoSSL cert.
