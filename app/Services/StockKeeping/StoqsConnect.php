<?php

namespace App\Services\StockKeeping;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * The automatic secret («اتصال خودکار»). The shop sends `connect` {webhook_url, claim} in hello;
 * StoqS pushes `site.connect` to the webhook, signed with the CLAIM, carrying a fresh secret. The
 * shop accepts it only while it holds a pending, unexpired, single-use claim, stores the secret
 * (the old one stays valid for an hour) and answers a proof only the new secret can make.
 *
 * Settings: stockkeeping.connect_claim {claim, created}, stockkeeping.connect {status, reason, at},
 * stockkeeping.reconnect_needed, stockkeeping.webhook_secret(_prev, _prev_until),
 * stockkeeping.connected_at.
 */
class StoqsConnect
{
    public const CLAIM_TTL = 1800;

    public const PREV_TTL = 3600;

    private const CLAIM_KEY = 'stockkeeping.connect_claim';

    public static function webhookUrl(): string
    {
        return rtrim((string) config('app.url'), '/').'/webhooks/stoqs';
    }

    /** The secrets that count now: the current one, and the previous one for an hour after a rotation. */
    public static function secrets(): array
    {
        $cur = (string) config('stockkeeping.webhook_secret', '');
        $out = $cur !== '' ? [$cur] : [];
        $prev = (string) Setting::get('stockkeeping.webhook_secret_prev', '');
        if ($prev !== '' && $prev !== $cur && (int) Setting::get('stockkeeping.webhook_secret_prev_until', 0) > time()) {
            $out[] = $prev;
        }

        return $out;
    }

    public static function needsConnect(): bool
    {
        return (string) config('stockkeeping.webhook_secret', '') === '' || (bool) Setting::get('stockkeeping.reconnect_needed', false);
    }

    /** The `connect` block for hello (null when not needed and not forced); keeps the pending claim. */
    public static function block(bool $force = false): ?array
    {
        if (! $force && ! self::needsConnect()) {
            return null;
        }
        $pending = Setting::get(self::CLAIM_KEY);
        if (is_array($pending) && ! empty($pending['claim']) && time() - (int) ($pending['created'] ?? 0) < self::CLAIM_TTL - 300) {
            $claim = (string) $pending['claim'];   // still fresh: the same claim again
        } else {
            $claim = bin2hex(random_bytes(32));
            Setting::put(self::CLAIM_KEY, ['claim' => $claim, 'created' => time()]);
        }

        return ['webhook_url' => self::webhookUrl(), 'claim' => $claim];
    }

    /** Remember what hello answered about `connect` (only when this hello sent one). */
    public static function noteAnswer(array $answer): void
    {
        $c = (array) ($answer['connect'] ?? []);
        $status = (string) ($c['status'] ?? '');
        if ($status === '') {
            return;
        }
        Setting::put('stockkeeping.connect', ['status' => $status, 'reason' => isset($c['reason']) ? (string) $c['reason'] : null, 'at' => now()->toIso8601String()]);
        if ($status === 'refused') {
            Setting::put(self::CLAIM_KEY, null);
        }
    }

    /** A signed report StoqS refused with 401 signature_*: connect again at the next hello. */
    public static function reconnectNeeded(): void
    {
        if (! Setting::get('stockkeeping.reconnect_needed', false)) {
            Setting::put('stockkeeping.reconnect_needed', true);
        }
    }

    /** 401 body with error «signature_…» (a string, or {code}). */
    public static function isSignatureRefusal(int $status, array $json): bool
    {
        if ($status !== 401) {
            return false;
        }
        $e = $json['error'] ?? '';
        $code = is_array($e) ? (string) ($e['code'] ?? '') : (string) $e;

        return str_starts_with($code, 'signature_');
    }

    /** @return array{state: string, reason: ?string, at: ?string, connected_at: ?string} state: connected|waiting|connecting|refused|not_connected */
    public static function status(): array
    {
        $c = (array) Setting::get('stockkeeping.connect', []);
        $status = (string) ($c['status'] ?? '');
        $has = (string) config('stockkeeping.webhook_secret', '') !== '' && ! Setting::get('stockkeeping.reconnect_needed', false);
        $state = $has ? 'connected' : match ($status) {
            'approval_needed' => 'waiting',
            'pending' => 'connecting',
            'refused' => 'refused',
            default => 'not_connected',
        };

        return ['state' => $state, 'reason' => $c['reason'] ?? null, 'at' => $c['at'] ?? null, 'connected_at' => Setting::get('stockkeeping.connected_at')];
    }

    /**
     * POST site.connect: the proof to answer, or null (refused: nothing stored). $signature is the
     * X-Stoqs-Signature header.
     *
     * @return array{proof: string}|null
     */
    public static function accept(string $raw, string $signature): ?array
    {
        $body = json_decode($raw, true);
        if (! is_array($body) || ($body['event'] ?? '') !== 'site.connect') {
            return null;
        }
        $row = DB::table('settings')->where('key', self::CLAIM_KEY)->first();
        $pending = $row ? json_decode((string) $row->value, true) : null;
        $claim = is_array($pending) ? (string) ($pending['claim'] ?? '') : '';
        if (! preg_match('/^[0-9a-f]{64}$/', $claim) || time() - (int) ($pending['created'] ?? 0) >= self::CLAIM_TTL) {
            return null;
        }
        $data = (array) ($body['data'] ?? []);
        if (! hash_equals(substr(hash('sha256', $claim), 0, 16), (string) ($data['claim_id'] ?? ''))
            || ! str_starts_with($signature, 'sha256=')
            || ! hash_equals(hash_hmac('sha256', $raw, $claim), substr($signature, 7))) {
            return null;
        }
        $secret = (string) ($data['secret'] ?? '');
        $webhookId = (string) ($data['webhook_id'] ?? '');
        if ($secret === '' || strlen($secret) > 200 || $webhookId === '') {
            return null;
        }
        // single use: only the request that empties the claim row goes on
        $taken = DB::table('settings')->where('key', self::CLAIM_KEY)->where('value', $row->value)->update(['value' => 'null']);
        if ($taken !== 1) {
            return null;
        }
        $old = (string) config('stockkeeping.webhook_secret', '');
        $values = [
            'stockkeeping.webhook_secret' => $secret,
            'stockkeeping.reconnect_needed' => false,
            'stockkeeping.connect' => ['status' => 'connected', 'reason' => null, 'at' => now()->toIso8601String()],
            'stockkeeping.connected_at' => now()->toIso8601String(),
        ];
        if ($old !== '' && $old !== $secret) {
            $values['stockkeeping.webhook_secret_prev'] = $old;
            $values['stockkeeping.webhook_secret_prev_until'] = time() + self::PREV_TTL;
        }
        Setting::putMany($values);
        config(['stockkeeping.webhook_secret' => $secret]);
        Cache::forget('settings:all');

        return ['proof' => hash_hmac('sha256', "connected\n".$webhookId."\n".$claim, $secret)];
    }

    /** Whether $signature (sha256=…) is the HMAC of $raw under a secret that counts now. */
    public static function bodySignatureOk(string $signature, string $raw): bool
    {
        if (! str_starts_with($signature, 'sha256=')) {
            return false;
        }
        foreach (self::secrets() as $secret) {
            if (hash_equals(hash_hmac('sha256', $raw, $secret), substr($signature, 7))) {
                return true;
            }
        }

        return false;
    }
}
