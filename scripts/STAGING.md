# Staging on cPanel (staging.chiiaco.com)

Goal: a parallel copy of the site on the SAME cPanel account, with its own
DB, its own `.env`, and its own subdomain — so you can deploy + smoke-test
risky changes without ever touching production.

This document is operational: most of it is one-time cPanel clicks, none of
it is committed code.

---

## 1 · One-time cPanel setup (≈10 min)

### 1.1 Subdomain
- cPanel → **Subdomains** → create `staging.chiiaco.com`
- Document root: `/home/chiiac/staging_public_html`
- Leave **Create automatically** off; we lay the files out by hand below.

### 1.2 Database
- cPanel → **MySQL® Databases** → create:
  - DB: `chiiac_staging`
  - User: `chiiac_staging` (give it ALL PRIVILEGES on the staging DB)
- Copy the password into a password manager.

### 1.3 Filesystem layout (mirror production)
SSH or File Manager:

```
/home/chiiac/
├── chiiaco_app/                  ← production code  (untouched)
├── public_html/                  ← prod public
├── staging_app/                  ← NEW: staging code (this is what deploy targets)
│   └── .env                      ← upload ONCE, never touched by deploy
└── staging_public_html/          ← NEW: staging web root
    ├── index.php                 ← rewritten by deploy.sh to boot ../staging_app
    └── storage → ../staging_app/storage/app/public   (symlink)
```

Steps:
```bash
mkdir -p /home/chiiac/staging_app
mkdir -p /home/chiiac/staging_public_html
ln -s ../staging_app/storage/app/public /home/chiiac/staging_public_html/storage
```

### 1.4 Staging `.env`
Copy `.env` from prod, then change the lines below. **Never** point at the
production DB or production webhook secrets — staging would otherwise
mutate live data.

```env
APP_ENV=staging
APP_URL=https://staging.chiiaco.com
APP_DEBUG=false              # never true on a public URL, even on staging

DB_DATABASE=chiiac_staging
DB_USERNAME=chiiac_staging
DB_PASSWORD=<from §1.2>

# Disable all outbound notifications by default — staging tests
# shouldn't text customers, push to Telegram, or report to StoqS.
SMS_DRIVER=log
TELEGRAM_BOT_TOKEN=
TELEGRAM_RELAY_URL=
STOCKKEEPING_ENABLED=false

# Sentry — use a separate project (NOT the prod DSN), or leave empty.
SENTRY_LARAVEL_DSN=
```

Upload as `/home/chiiac/staging_app/.env` (chmod 600).

### 1.5 Seed the DB
On your laptop, after step 1.4:
```bash
# From the chiiaco-website repo:
mysqldump --skip-add-drop-table --no-create-info <prod creds> | \
  mysql -h <staging host> -u chiiac_staging -p chiiac_staging
```
Or simply run migrations + seeders on the empty DB and create a test admin.

---

## 2 · Deploy to staging

`scripts/deploy.sh` targets production by default. To deploy to staging,
override the two paths and the verifier URL on the command line — the
script accepts them as env vars (see `scripts/.stoqs-deploy.example` and
`scripts/DEPLOY.md` for the variable names). Working invocation:

```bash
DEPLOY_REMOTE_APP_DIR=/home/chiiac/staging_app \
DEPLOY_REMOTE_PUBLIC_DIR=/home/chiiac/staging_public_html \
DEPLOY_VERIFY_URL=https://staging.chiiaco.com/up \
bash scripts/deploy.sh
```

Add `--migrate` when the change touches DB schema, same as prod.

After the first successful deploy, alias it for convenience — `.bashrc`:
```bash
alias deploy-staging='DEPLOY_REMOTE_APP_DIR=/home/chiiac/staging_app \
  DEPLOY_REMOTE_PUBLIC_DIR=/home/chiiac/staging_public_html \
  DEPLOY_VERIFY_URL=https://staging.chiiaco.com/up \
  bash scripts/deploy.sh'
```

---

## 3 · The staging discipline

1. Risky / data-affecting change → branch → push → `deploy-staging` → walk
   the affected pages on `staging.chiiaco.com` → only then `bash
   scripts/deploy.sh` to prod.
2. **Never reuse production credentials in staging `.env`** (gateway keys,
   Telegram token, SMS API key). Staging should be allowed to crash loudly.
3. Refresh staging from prod every few weeks so the data isn't stale —
   `mysqldump` + import overwrites the staging DB safely (no FK gymnastics
   needed because the staging URL doesn't appear in any of the JSON
   payloads).

---

## 4 · Tearing down staging
```bash
rm -rf /home/chiiac/staging_app /home/chiiac/staging_public_html
```
Then drop the DB + subdomain in cPanel. No production artefact is touched.
