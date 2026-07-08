<?php

/**
 * Racket Club one-shot deployer — token-guarded, self-deleting.
 *
 * scripts/deploy.sh uploads this file fresh to public_html on every deploy (with
 * a random token + the app-dir name baked in), uploads the release zip to the
 * account home, then triggers this ONCE over HTTPS. It:
 *   1. unzips the release into the app dir (above web root),
 *   2. wires public_html (overlays public/*, points index.php at the app),
 *   3. ensures writable storage/ + bootstrap/cache (never wipes uploads/logs),
 *   4. runs migrations (optional) + config/view cache via a bootstrapped artisan,
 *   5. busts opcache,
 *   6. deletes the zip and itself.
 *
 * No SSH required. The token is single-use (new per deploy) and the script
 * removes itself, so it is not left reachable.
 */
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$TOKEN      = '__DEPLOY_TOKEN__';
$APP_DIR    = '__APP_DIR__';
$DO_MIGRATE = (($_GET['migrate'] ?? '0') === '1');
$DO_SEED    = (($_GET['seed'] ?? '0') === '1');

// ---- guard ------------------------------------------------------------------
if ($TOKEN === '__DEPLOY_'.'TOKEN__' || ! hash_equals($TOKEN, (string) ($_GET['token'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'msg' => 'forbidden']);
    exit;
}

@set_time_limit(900);
@ignore_user_abort(true);

$log = [];
$errors = [];                       // server-side failures (migrate, caches, …) surfaced to the client
$log[] = 'PHP '.PHP_VERSION;

// Surface PHP fatals as JSON instead of a bare 500 white-screen.
register_shutdown_function(function () use (&$log) {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        if (! headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(['ok' => false, 'msg' => 'PHP fatal: '.$e['message'].' @ '.basename($e['file']).':'.$e['line'], 'log' => $log], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
});

$fail = function (string $msg) use (&$log): void {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => $msg, 'log' => $log], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
};

$public = __DIR__;                  // .../public_html
$home   = dirname($public);         // .../<account home>
$appDir = $home.'/'.$APP_DIR;       // .../racketclub_app
$zip    = $home.'/racketclub-deploy.zip';

if (! is_file($zip)) {
    $fail("release zip not found: $zip");
}

// ---- 1) extract release into the app dir (overlay) --------------------------
if (! is_dir($appDir) && ! @mkdir($appDir, 0755, true)) {
    $fail("cannot create app dir: $appDir");
}
$za = new ZipArchive();
if ($za->open($zip) !== true) {
    $fail("cannot open zip: $zip");
}
$count = $za->numFiles;
if (! $za->extractTo($appDir)) {
    $za->close();
    $fail("extract failed into $appDir");
}
$za->close();
$log[] = "extracted $count entries";

// ---- 2) .env must exist (you upload it once; never created/overwritten) ------
if (! is_file($appDir.'/.env')) {
    $fail("missing $appDir/.env — upload your production .env once (template: .env.production.example).");
}

// ---- 3) writable runtime dirs (created if missing; never wiped) --------------
foreach (['framework/cache/data', 'framework/sessions', 'framework/views', 'logs', 'app/public'] as $d) {
    @mkdir($appDir.'/storage/'.$d, 0775, true);
}
@mkdir($appDir.'/bootstrap/cache', 0775, true);

// ---- 4) wire public_html ----------------------------------------------------
rcopy($appDir.'/public', $public);
file_put_contents($public.'/index.php', indexPhp($APP_DIR));
// Storage symlink — self-healing. rcopy above can leave a stale/broken symlink
// (pointing at the build machine) or a copied directory where public_html/storage
// should be; either makes /storage/* 404. Ensure it's a symlink to the app's real
// public storage, and that the target exists.
$linkPath = $public.'/storage';
$target = $appDir.'/storage/app/public';
@mkdir($target, 0775, true);
if (! (is_link($linkPath) && @readlink($linkPath) === $target)) {
    if (is_dir($linkPath) && ! is_link($linkPath)) {
        delTree($linkPath);              // a copied dir (build junk) shadowing the link
    } elseif (is_link($linkPath) || file_exists($linkPath)) {
        @unlink($linkPath);              // broken / wrong-target symlink or a file
    }
    @symlink($target, $linkPath);
}
$log[] = 'public_html wired (storage link → '.(is_link($linkPath) ? @readlink($linkPath) : 'FAILED').')';

// ---- 5) Laravel: caches + optional migrate ----------------------------------
chdir($appDir);
try {
    require $appDir.'/vendor/autoload.php';
    /** @var \Illuminate\Foundation\Application $app */
    $app = require $appDir.'/bootstrap/app.php';
    $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);

    $artisan = function (string $cmd, array $params = []) use ($kernel, &$log, &$errors): void {
        try {
            $code = $kernel->call($cmd, $params);
            $out = trim($kernel->output());
            $log[] = "[$cmd] exit $code".($out !== '' ? ' — '.preg_replace('/\s+/', ' ', $out) : '');
            if ($code !== 0) {
                $errors[] = "[$cmd] exited with code $code".($out !== '' ? ': '.preg_replace('/\s+/', ' ', $out) : '');
            }
        } catch (\Throwable $e) {
            $msg = "[$cmd] ERROR ".$e->getMessage();
            $log[] = $msg;
            $errors[] = $msg;
        }
    };

    $artisan('config:clear');
    $artisan('cache:clear');
    $artisan('storage:link');
    if ($DO_MIGRATE) {
        $artisan('migrate', ['--force' => true]);
    }
    if ($DO_SEED) {
        $artisan('db:seed', ['--class' => 'Database\\Seeders\\ProductionSeeder', '--force' => true]);
    }
    $artisan('config:cache');
    $artisan('view:cache');
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'msg' => 'bootstrap failed: '.$e->getMessage().' @ '.basename($e->getFile()).':'.$e->getLine(), 'log' => $log], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

// ---- 6) bust opcache so new code is live immediately ------------------------
if (function_exists('opcache_reset')) {
    @opcache_reset();
    $log[] = 'opcache reset';
}

// ---- 7) clean up ------------------------------------------------------------
@unlink($zip);
@unlink(__FILE__);

// A failed migration/cache leaves code live but the DB/caches out of sync — fail
// loudly so the deploy doesn't look successful.
if ($errors) {
    http_response_code(500);
}
echo json_encode([
    'ok' => empty($errors),
    'msg' => $errors ? 'deployed WITH ERRORS — review' : 'deployed',
    'migrated' => $DO_MIGRATE,
    'errors' => $errors,
    'log' => $log,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

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
    if (! is_dir($src)) {
        return;
    }
    @mkdir($dst, 0755, true);
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    foreach ($it as $item) {
        $target = $dst.'/'.$it->getSubPathName();
        if ($item->isDir()) {
            @mkdir($target, 0755, true);
        } else {
            @copy($item->getPathname(), $target);
        }
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
