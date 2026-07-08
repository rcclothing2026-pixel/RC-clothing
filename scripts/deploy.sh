#!/usr/bin/env bash
# =============================================================================
# scripts/deploy.sh — one-command deploy for racketclub.ir (cPanel / FTP, no SSH).
#
#   bash scripts/deploy.sh --pull        # pull latest, then build + deploy
#   bash scripts/deploy.sh               # build + deploy the current checkout
#
# Builds a release zip (composer --no-dev + vite build), uploads ONE zip over
# FTP, uploads a token-guarded one-shot PHP extractor to public_html, triggers
# it once over HTTPS (unzip → wire public_html → migrate → cache → opcache),
# and the extractor deletes the zip + itself. Then verifies the site is up.
# Every run ships code + runs pending migrations + rebuilds caches, so future
# updates (new features, DB/schema changes) go live the same way.
#
# NOTE (Iran network): GitHub is blocked, the cPanel host wants VPN OFF. So the
# usual flow is: VPN ON → `git pull` → VPN OFF → `bash scripts/deploy.sh`.
# Use `--pull` only when GitHub is reachable without blocking the FTP host.
#
# FIRST TIME: do the one-time setup in scripts/DEPLOY-RACKETCLUB.md (PHP 8.3,
# create DB, upload .env once, add cron) BEFORE running this.
#
# Flags:
#   (default)      AUTO: ships app code always, and only re-ships vendor/ or
#                  images+fonts when they actually changed since the last deploy.
#                  So a normal deploy is a few MB — no flag needed.
#   --pull         git pull --ff-only the current branch before building
#   --full         force-ship everything (vendor + images + fonts)
#   --code-only    force app-code-only (skip vendor + images + fonts)
#   --no-build     reuse the newest RELEASE/*.zip instead of rebuilding
#   --no-migrate   skip database migrations (files/assets only)
#   --seed         run the production seeder after migrating (first deploy)
#   --no-verify    skip the post-deploy HTTP check
#   --help
# =============================================================================
set -euo pipefail
set -E   # functions/subshells inherit the ERR trap

bold=$'\033[1m'; red=$'\033[31m'; grn=$'\033[32m'; ylw=$'\033[33m'; dim=$'\033[2m'; rst=$'\033[0m'
hr()     { printf '%s──────────────────────────────────────────────────────────%s\n' "$dim" "$rst"; }
header() { echo; hr; printf '  %s%s%s\n' "$bold" "$1" "$rst"; hr; }
ok()     { printf '  %s✓%s %s\n' "$grn" "$rst" "$1"; }
warn()   { printf '  %s⚠%s %s\n' "$ylw" "$rst" "$1"; }
die()    { printf '  %s✗ %s%s\n' "$red" "$1" "$rst" >&2; exit 1; }

# Never fail silently: report the command + exit code on any unexpected abort.
trap 'rc=$?; [ "$rc" -ne 0 ] && printf "\n  %s✗ aborted (exit %s): %s%s\n" "$red" "$rc" "${BASH_COMMAND}" "$rst" >&2' ERR

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$HERE"

# temp artefacts cleaned on exit (init empty so the trap is safe under `set -u`)
STAGE=""; TMP_PHP=""
trap 'rm -rf "$STAGE" "$TMP_PHP" 2>/dev/null || true' EXIT
ZIP="$HERE/RELEASE/racketclub-deploy.zip"

# ----- args -----------------------------------------------------------------
# DO_VENDOR / DO_ASSETS default to 'auto': the build decides from a content hash
# whether PHP deps (composer.lock) or static assets (public/img, public/fonts)
# actually changed since the last successful deploy, and only ships them if so.
# So `bash scripts/deploy.sh` is always minimal AND correct — no flag needed.
DO_BUILD=1; DO_MIGRATE=1; DO_VERIFY=1; DO_SEED=0; DO_PULL=0; DO_VENDOR=auto; DO_ASSETS=auto
for a in "$@"; do
  case "$a" in
    --pull)         DO_PULL=1 ;;
    --no-build)     DO_BUILD=0 ;;
    --no-migrate)   DO_MIGRATE=0 ;;
    --no-verify)    DO_VERIFY=0 ;;
    --seed)         DO_SEED=1 ;;
    --full)         DO_VENDOR=1; DO_ASSETS=1 ;;
    --code-only|--fast) DO_VENDOR=0; DO_ASSETS=0 ;;
    --help|-h)    sed -n '2,36p' "${BASH_SOURCE[0]}" | sed 's/^# \{0,1\}//'; exit 0 ;;
    *) die "unknown flag: $a (try --help)" ;;
  esac
done

# ----- [0] optional git pull ------------------------------------------------
if [ "$DO_PULL" -eq 1 ]; then
  header "[0] Pull latest"
  command -v git >/dev/null || die "git not found in PATH"
  BR="$(git rev-parse --abbrev-ref HEAD)"
  git pull --ff-only origin "$BR" || die "git pull failed (GitHub reachable? on branch $BR?)"
  ok "pulled origin/$BR"
fi

# ----- [1] credentials ------------------------------------------------------
header "[1/6] Credentials"
CREDS="${HOME}/.racketclub-deploy"
[ -f "$CREDS" ] || die "missing $CREDS
     cp scripts/.racketclub-deploy.example ~/.racketclub-deploy && chmod 600 ~/.racketclub-deploy
     then fill in the FTP password."
if stat -f '%Lp' "$CREDS" >/dev/null 2>&1; then MODE="$(stat -f '%Lp' "$CREDS")"; else MODE="$(stat -c '%a' "$CREDS")"; fi
case "$MODE" in 600|400) ;; *) die "$CREDS is mode $MODE — too open. Run: chmod 600 $CREDS" ;; esac
# shellcheck disable=SC1090
source "$CREDS"
: "${FTP_HOST:?set FTP_HOST in ~/.racketclub-deploy}"
: "${FTP_USER:?set FTP_USER in ~/.racketclub-deploy}"
: "${FTP_PASS:?set FTP_PASS in ~/.racketclub-deploy}"
: "${PUBLIC_HOST:?set PUBLIC_HOST in ~/.racketclub-deploy}"
PUBLIC_DIR="${PUBLIC_DIR-public_html}"
APP_DIR="${APP_DIR-racketclub_app}"
FTP_SECURE="${FTP_SECURE-1}"        # 1 = explicit FTPS (AUTH TLS), 0 = plain FTP
FTP_INSECURE="${FTP_INSECURE-1}"    # 1 = accept self-signed certs
# Pre-DNS deploy: if racketclub.ir doesn't point here yet, set TRIGGER_IP=<server ip>
# so the HTTPS trigger/verify resolve to this server (cert not valid yet → -k).
WEB_OPTS=()
if [ -n "${TRIGGER_IP-}" ]; then
  WEB_OPTS+=(--resolve "${PUBLIC_HOST}:443:${TRIGGER_IP}" -k)
fi
ok "loaded creds for ${FTP_USER}@${FTP_HOST} → https://${PUBLIC_HOST}"
[ -n "${TRIGGER_IP-}" ] && warn "pre-DNS mode: web requests resolve ${PUBLIC_HOST} → ${TRIGGER_IP} (insecure TLS)"
[ -n "$(git status --porcelain 2>/dev/null)" ] && warn "working tree has uncommitted changes — they WILL ship in this zip."

# ----- resolve auto vendor/assets from a content-hash of the last deploy -----
SHA=shasum; command -v shasum >/dev/null 2>&1 || SHA=sha1sum
STATE="$HERE/RELEASE/.deploy-state"
CUR_COMPOSER="$($SHA composer.lock 2>/dev/null | awk '{print $1}')"
# hash the CONTENTS of every image/font so a same-name replacement is caught too
CUR_ASSETS="$(find public/img public/fonts -type f -exec $SHA {} + 2>/dev/null | sort | $SHA 2>/dev/null | awk '{print $1}')"
LAST_COMPOSER=""; LAST_ASSETS=""
# shellcheck disable=SC1090
[ -f "$STATE" ] && source "$STATE"
if [ "$DO_VENDOR" = auto ]; then
  { [ -n "$LAST_COMPOSER" ] && [ "$LAST_COMPOSER" = "$CUR_COMPOSER" ]; } && DO_VENDOR=0 || DO_VENDOR=1
fi
if [ "$DO_ASSETS" = auto ]; then
  { [ -n "$LAST_ASSETS" ] && [ "$LAST_ASSETS" = "$CUR_ASSETS" ]; } && DO_ASSETS=0 || DO_ASSETS=1
fi
[ "$DO_VENDOR" -eq 1 ] && ok "PHP deps: shipping vendor/ (composer.lock changed or first deploy)" \
                       || ok "PHP deps unchanged → keeping the server's vendor/"
[ "$DO_ASSETS" -eq 1 ] && ok "static assets: shipping public/img + public/fonts (changed or first deploy)" \
                       || ok "images/fonts unchanged → keeping the server's copies"

# ----- [2] build release zip ------------------------------------------------
header "[2/6] Build release zip"
if [ "$DO_BUILD" -eq 1 ]; then
  command -v composer >/dev/null || die "composer not found in PATH"
  command -v npm >/dev/null      || die "npm not found in PATH"
  command -v rsync >/dev/null    || die "rsync not found in PATH"
  command -v zip >/dev/null      || die "zip not found in PATH"

  # Preflight: a merge-conflict marker or PHP parse error ships as an HTTP 500
  # storm on production. Refuse to build if either is present. Cheap; catches
  # the tonight-of-2026-07-05 class of accident.
  if grep -rHnE '^(<<<<<<<|=======|>>>>>>>) ?' app config routes bootstrap 2>/dev/null; then
    die "unresolved merge conflict markers above — resolve before deploying"
  fi
  while IFS= read -r f; do
    php -l "$f" >/dev/null 2>&1 || { php -l "$f"; die "PHP syntax error in $f"; }
  done < <(find app config routes bootstrap -name '*.php' 2>/dev/null)
  ok "preflight: no merge markers, no PHP syntax errors"

  if [ ! -d node_modules ]; then
    ok "installing npm deps (first build; may be slow)…"
    npm install || die "npm install failed — run 'npm install' manually to see the error"
  fi
  ok "vite build (production assets)…"
  npm run build || die "npm run build failed (output above) — try 'npm install' then re-run"

  STAGE="$(mktemp -d)"
  ok "staging app (excluding dev/local files)…"
  # design-system/ is brand-book + client source material (PDFs, fonts, ~70M) —
  # never runtime; the assets the site uses already live in public/. Always skip.
  RSYNC_EXCLUDES=(
    --exclude='.git' --exclude='.github' --exclude='node_modules'
    --exclude='.claude' --exclude='.cursor'
    --exclude='.env' --exclude='.env.*'
    --exclude='storage' --exclude='tests' --exclude='scripts' --exclude='RELEASE'
    --exclude='bootstrap/cache/*' --exclude='database/*.sqlite'
    --exclude='public/storage' --exclude='public/hot'
    --exclude='vendor'
    --exclude='design-system'
    --exclude='.DS_Store' --exclude='auth.json'
  )
  # Static assets (campaign photos + self-hosted fonts) only ship when they
  # actually changed (auto-detected above) — otherwise the server keeps them.
  if [ "$DO_ASSETS" -eq 0 ]; then
    RSYNC_EXCLUDES+=( --exclude='public/img' --exclude='public/fonts' )
  fi
  rsync -a "${RSYNC_EXCLUDES[@]}" ./ "$STAGE/"

  if [ "$DO_VENDOR" -eq 1 ]; then
    ok "composer install --no-dev (prune dev deps, optimize autoloader)…"
    composer install --no-dev --optimize-autoloader --no-interaction --no-scripts \
      --working-dir="$STAGE" >/dev/null 2>&1 || die "composer --no-dev failed in stage"
  fi

  mkdir -p RELEASE
  rm -f "$ZIP"
  ( cd "$STAGE" && zip -rqX "$ZIP" . )
  ok "built $ZIP ($(du -h "$ZIP" | cut -f1))"
else
  [ -f "$ZIP" ] || die "no $ZIP to reuse — drop --no-build"
  ok "reusing $ZIP ($(du -h "$ZIP" | cut -f1))"
fi

# ----- FTP helper -----------------------------------------------------------
ftp_put() { # $1 = local file, $2 = remote path under FTP root
  local args=(--ftp-pasv --connect-timeout 20 --max-time 900 -fsS)
  [ "$FTP_SECURE" = "1" ] && args+=(--ssl-reqd)
  [ "$FTP_INSECURE" = "1" ] && args+=(-k)
  local attempt rc
  for attempt in 1 2 3 4; do
    if curl "${args[@]}" -T "$1" -u "${FTP_USER}:${FTP_PASS}" "ftp://${FTP_HOST}/${2}"; then return 0; fi
    rc=$?
    [ "$attempt" -lt 4 ] && { warn "upload attempt $attempt failed (curl $rc) — retry in $((attempt*3))s"; sleep $((attempt*3)); }
  done
  return 1
}

# ----- [3] upload release zip → account home --------------------------------
header "[3/6] Upload release zip (FTP)"
ftp_put "$ZIP" "racketclub-deploy.zip" || die "zip upload failed.
     Check: VPN OFF · $FTP_HOST reachable · password current · try FTP_SECURE=0 in ~/.racketclub-deploy"
ok "uploaded racketclub-deploy.zip → account home"

# ----- [4] upload + trigger the one-shot extractor --------------------------
header "[4/6] Extract + migrate (server-side)"
# A URL-safe single-use token. Prefer PHP (always present for a Laravel build);
# fall back to openssl, then /dev/urandom — so no single tool is required.
if TOKEN="$(php -r 'echo bin2hex(random_bytes(20));' 2>/dev/null)" && [ -n "$TOKEN" ]; then :;
elif command -v openssl >/dev/null 2>&1; then TOKEN="$(openssl rand -hex 20)";
else TOKEN="$(head -c 20 /dev/urandom | od -An -tx1 | tr -d ' \n')"; fi
TMP_PHP="$(mktemp)"
sed -e "s/__DEPLOY_TOKEN__/${TOKEN}/g" -e "s/__APP_DIR__/${APP_DIR}/g" \
    scripts/server/deploy.php > "$TMP_PHP"
ftp_put "$TMP_PHP" "${PUBLIC_DIR}/deploy.php" || die "extractor upload failed"
ok "uploaded one-shot extractor → ${PUBLIC_DIR}/deploy.php"

MIG=0; [ "$DO_MIGRATE" -eq 1 ] && MIG=1
SEEDV=0; [ "$DO_SEED" -eq 1 ] && SEEDV=1
TRIGGER="https://${PUBLIC_HOST}/deploy.php?token=${TOKEN}&migrate=${MIG}&seed=${SEEDV}"
ok "triggering: ${dim}https://${PUBLIC_HOST}/deploy.php?token=…&migrate=${MIG}&seed=${SEEDV}${rst}"
RESP="$(curl ${WEB_OPTS[@]+"${WEB_OPTS[@]}"} -sS --max-time 900 "$TRIGGER" || true)"
if echo "$RESP" | grep -q '"ok":true'; then
  ok "server reported success${DO_MIGRATE:+ (migrate=$MIG)}"
  echo "$RESP" | sed 's/.*"log":\[//; s/\].*//; s/","/"\n        "/g; s/^/        /' | sed 's/^/  /' || true
else
  warn "server reported FAILURE — errors below:"
  # Surface the server-side errors array (migrate/cache failures) clearly.
  ERRS="$(echo "$RESP" | sed -n 's/.*"errors":\[\(.*\)\],"log".*/\1/p; s/.*"errors":\[\(.*\)\]}.*/\1/p' | head -1)"
  if [ -n "$ERRS" ]; then
    # split the JSON array on "," and print each as its own ✗ line (portable awk)
    echo "$ERRS" | awk -F'","' '{for(i=1;i<=NF;i++){gsub(/^"|"$/,"",$i); print "    ✗ " $i}}'
  else
    echo "${dim}${RESP}${rst}" | sed 's/^/    /'
  fi
  die "deploy trigger failed — code may be live but migrations/caches did NOT all succeed.
     Fix the cause above and re-run. (Missing-.env message? upload your production .env once — see scripts/DEPLOY.md.)"
fi

# ----- [5] verify -----------------------------------------------------------
header "[5/6] Verify"
if [ "$DO_VERIFY" -eq 1 ]; then
  CODE="$(curl ${WEB_OPTS[@]+"${WEB_OPTS[@]}"} -s -o /dev/null -w '%{http_code}' --max-time 60 "https://${PUBLIC_HOST}/" || echo 000)"
  case "$CODE" in
    200|301|302) ok "https://${PUBLIC_HOST}/ → HTTP $CODE" ;;
    *) warn "https://${PUBLIC_HOST}/ → HTTP $CODE (check the site + storage/logs/laravel.log)" ;;
  esac
  HEALTH="$(curl ${WEB_OPTS[@]+"${WEB_OPTS[@]}"} -s -o /dev/null -w '%{http_code}' --max-time 60 "https://${PUBLIC_HOST}/up" || echo 000)"
  [ "$HEALTH" = "200" ] && ok "/up health check → 200" || warn "/up → $HEALTH"
else
  warn "verify skipped (--no-verify)"
fi

# ----- [6] done -------------------------------------------------------------
header "[6/6] Done"
# Record what this deploy shipped so the next run's auto mode can skip unchanged
# vendor/assets. Only written on success (set -e aborts earlier on failure).
mkdir -p "$HERE/RELEASE"
{ if [ "$DO_VENDOR" -eq 1 ]; then printf 'LAST_COMPOSER=%s\n' "$CUR_COMPOSER"; else printf 'LAST_COMPOSER=%s\n' "${LAST_COMPOSER:-$CUR_COMPOSER}"; fi
  if [ "$DO_ASSETS" -eq 1 ]; then printf 'LAST_ASSETS=%s\n' "$CUR_ASSETS"; else printf 'LAST_ASSETS=%s\n' "${LAST_ASSETS:-$CUR_ASSETS}"; fi
} > "$STATE"
ok "deployed to https://${PUBLIC_HOST}"
echo "  ${dim}The release zip and the extractor were removed from the server.${rst}"
