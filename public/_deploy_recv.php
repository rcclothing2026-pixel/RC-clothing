<?php

/**
 * _deploy_recv.php — token-guarded HTTPS deploy receiver for chiiaco (Laravel).
 *
 * The Mac deploys over FTPS (scripts/deploy.sh). Cloud CI runners (GitHub
 * Actions) can't FTP into the Iran host, but HTTPS to the site works — so this
 * endpoint lets CI push a build over HTTPS: accept the release zip via POST,
 * verify a token, unzip into the app dir (above the web root), wire public_html,
 * run migrations + caches via a bootstrapped artisan, and bust opcache.
 *
 * Same trust model as the FTP one-shot extractor (scripts/server/deploy.php),
 * just over HTTPS and PERSISTENT (it lives in public/, so every deploy overlays
 * a fresh copy — self-updating). The token lives ONLY in
 * <home>/chiiaco_app/deploy-token.php on the server (gitignored, never shipped)
 * and in CI's DEPLOY_TOKEN secret — never in this file or the repo.
 *
 *   POST fields:  key=<token>   zip=@<release.zip>   [migrate=1]
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$public = __DIR__;                       // .../public_html (web root)
$home   = dirname($public);              // .../<account home>
$APP_DIR = 'chiiaco_app';
$appDir = $home.'/'.$APP_DIR;
$zipPath = $home.'/chiiaco-deploy.zip';

$log = [];
$errors = [];
$log[] = 'PHP '.PHP_VERSION;

$respond = function (int $code, bool $ok, string $msg) use (&$log, &$errors): void {
    http_response_code($code);
    echo json_encode([
        'ok' => $ok, 'msg' => $msg, 'errors' => $errors, 'log' => $log,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    echo "\n".($ok ? 'DONE' : 'FAILED')."\n";
    exit;
};

// ---- 1) HTTPS only ----------------------------------------------------------
$https = (! empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
      || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
      || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443');
if (! $https) {
    $respond(400, false, 'HTTPS required');
}

// ---- 2) load + verify the token (standalone file above the web root) --------
$tokfile = $appDir.'/deploy-token.php';
if (is_file($tokfile)) {
    require_once $tokfile;
}
if (! defined('DEPLOY_HTTP_TOKEN') || DEPLOY_HTTP_TOKEN === '') {
    $respond(403, false, 'deploy token not configured (create '.$APP_DIR.'/deploy-token.php)');
}
$key = (string) ($_POST['key'] ?? ($_GET['token'] ?? ''));
if ($key === '' || ! hash_equals((string) DEPLOY_HTTP_TOKEN, $key)) {
    $respond(403, false, 'forbidden');
}

@set_time_limit(900);
@ignore_user_abort(true);

$DO_MIGRATE = ((string) ($_POST['migrate'] ?? $_GET['migrate'] ?? '0')) === '1';

// ---- 3) receive the uploaded zip -------------------------------------------
$err = $_FILES['zip']['error'] ?? UPLOAD_ERR_NO_FILE;
if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
    $respond(413, false, "zip exceeds this host's upload limit — raise upload_max_filesize/post_max_size (public_html/.user.ini)");
}
if (empty($_FILES['zip']) || $err !== UPLOAD_ERR_OK || ! is_uploaded_file($_FILES['zip']['tmp_name'])) {
    $respond(400, false, "no zip uploaded (POST file field 'zip', error=$err)");
}
if (! @move_uploaded_file($_FILES['zip']['tmp_name'], $zipPath)) {
    $respond(500, false, 'could not save upload to '.$zipPath);
}
$log[] = 'uploaded '.number_format((float) filesize($zipPath)).' bytes';

// ---- 4) extract release into the app dir (overlay) --------------------------
if (! class_exists('ZipArchive')) {
    @unlink($zipPath);
    $respond(500, false, 'ZipArchive not available on this PHP');
}
if (! is_dir($appDir) && ! @mkdir($appDir, 0755, true)) {
    $respond(500, false, "cannot create app dir: $appDir");
}
$za = new ZipArchive();
if ($za->open($zipPath) !== true) {
    @unlink($zipPath);
    $respond(500, false, "cannot open zip: $zipPath");
}
$count = $za->numFiles;
if (! $za->extractTo($appDir)) {
    $za->close();
    @unlink($zipPath);
    $respond(500, false, "extract failed into $appDir");
}
$za->close();
$log[] = "extracted $count entries";

// ---- 5) .env must exist (uploaded once; never created/overwritten) ----------
if (! is_file($appDir.'/.env')) {
    @unlink($zipPath);
    $respond(500, false, "missing $APP_DIR/.env — upload your production .env once");
}

// ---- 6) writable runtime dirs (created if missing; never wiped) -------------
foreach (['framework/cache/data', 'framework/sessions', 'framework/views', 'logs', 'app/public'] as $d) {
    @mkdir($appDir.'/storage/'.$d, 0775, true);
}
@mkdir($appDir.'/bootstrap/cache', 0775, true);

// ---- 7) wire public_html ----------------------------------------------------
rcopy($appDir.'/public', $public);
file_put_contents($public.'/index.php', indexPhp($APP_DIR));
$linkPath = $public.'/storage';
$target = $appDir.'/storage/app/public';
@mkdir($target, 0775, true);
if (! (is_link($linkPath) && @readlink($linkPath) === $target)) {
    if (is_dir($linkPath) && ! is_link($linkPath)) {
        delTree($linkPath);
    } elseif (is_link($linkPath) || file_exists($linkPath)) {
        @unlink($linkPath);
    }
    @symlink($target, $linkPath);
}
$log[] = 'public_html wired (storage link → '.(is_link($linkPath) ? @readlink($linkPath) : 'FAILED').')';

// ---- 8) Laravel caches + optional migrate -----------------------------------
chdir($appDir);
try {
    require $appDir.'/vendor/autoload.php';
    $app = require $appDir.'/bootstrap/app.php';
    $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
    $artisan = function (string $cmd, array $params = []) use ($kernel, &$log, &$errors): void {
        try {
            $code = $kernel->call($cmd, $params);
            $out = trim($kernel->output());
            $log[] = "[$cmd] exit $code".($out !== '' ? ' — '.preg_replace('/\s+/', ' ', $out) : '');
            if ($code !== 0) {
                $errors[] = "[$cmd] exited $code".($out !== '' ? ': '.preg_replace('/\s+/', ' ', $out) : '');
            }
        } catch (\Throwable $e) {
            $errors[] = $log[] = "[$cmd] ERROR ".$e->getMessage();
        }
    };
    $artisan('config:clear');
    $artisan('cache:clear');
    // Clear any stale cached route table so new routes (e.g. the live-preview
    // endpoint) resolve after deploy — bootstrap/cache is excluded from the
    // upload, so a pre-existing routes-vN.php would otherwise never update.
    $artisan('route:clear');
    $artisan('storage:link');
    if ($DO_MIGRATE) {
        $artisan('migrate', ['--force' => true]);
    }
    $artisan('config:cache');
    $artisan('view:cache');
} catch (\Throwable $e) {
    @unlink($zipPath);
    $respond(500, false, 'bootstrap failed: '.$e->getMessage().' @ '.basename($e->getFile()).':'.$e->getLine());
}

// ---- 9) opcache + cleanup ---------------------------------------------------
if (function_exists('opcache_reset')) {
    @opcache_reset();
    $log[] = 'opcache reset';
}
@unlink($zipPath);

$respond($errors ? 500 : 200, empty($errors), $errors ? 'deployed WITH ERRORS — review' : 'deployed');

// ---- helpers ----------------------------------------------------------------
function delTree(string $dir): void
{
    if (is_link($dir) || is_file($dir)) { @unlink($dir); return; }
    if (! is_dir($dir)) { return; }
    foreach (scandir($dir) ?: [] as $f) {
        if ($f === '.' || $f === '..') { continue; }
        $p = $dir.'/'.$f;
        (is_dir($p) && ! is_link($p)) ? delTree($p) : @unlink($p);
    }
    @rmdir($dir);
}

function rcopy(string $src, string $dst): void
{
    if (! is_dir($src)) { return; }
    @mkdir($dst, 0755, true);
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $item) {
        $target = $dst.'/'.$it->getSubPathName();
        $item->isDir() ? @mkdir($target, 0755, true) : @copy($item->getPathname(), $target);
    }
}

function indexPhp(string $appDir): string
{
    return <<<PHP
<?php
use Illuminate\\Foundation\\Application;
use Illuminate\\Http\\Request;
define('LARAVEL_START', microtime(true));
\$__base = __DIR__.'/../{$appDir}';
if (file_exists(\$__m = \$__base.'/storage/framework/maintenance.php')) require \$__m;
require \$__base.'/vendor/autoload.php';
/** @var Application \$app */
\$app = require_once \$__base.'/bootstrap/app.php';
\$app->handleRequest(Request::capture());
PHP;
}
