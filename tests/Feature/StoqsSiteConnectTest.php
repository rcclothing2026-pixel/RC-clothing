<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCampaignReadAccess;
use App\Models\Setting;
use App\Models\User;
use App\Services\StockKeeping\StoqsApi;
use App\Services\StockKeeping\StoqsConnect;
use App\Services\StockKeeping\StoqsSites;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * StoqS 5.2.128 automatic secret (the shop side): hello carries `connect` {webhook_url, claim};
 * StoqS then pushes site.connect signed with the claim. The shop honours it only while it holds a
 * pending, unexpired, single-use claim, stores the new secret (the old one lives on for an hour)
 * and answers a proof. Signed reports carry a nonce; a 401 signature_* means «connect again».
 */
class StoqsSiteConnectTest extends TestCase
{
    use RefreshDatabase;

    private const OLD = 'old-secret';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'stockkeeping.enabled' => true, 'stockkeeping.base_url' => 'https://stoqs.test', 'stockkeeping.api_key' => 'k',
            'stockkeeping.webhook_secret' => '', 'app.url' => 'https://shop.test', 'scout.driver' => null,
            'services.ig_catalog.key' => 'catalog-key',
        ]);
        Http::preventStrayRequests();
        Cache::lock('scheduler:web-kick', 600)->get();
    }

    /** Hello with the claim StoqS will push to: the claim the shop sent. */
    private function hello(string $status = 'pending', bool $force = false): string
    {
        Http::fake(['stoqs.test/api/v1/sites/hello' => Http::response(['ok' => true, 'connect' => ['status' => $status], 'connected' => false, 'trusted' => true])]);
        StoqsSites::hello($force);
        $claim = '';
        Http::assertSent(function (Request $r) use (&$claim) {
            $claim = (string) ($r['connect']['claim'] ?? $claim);

            return str_ends_with($r->url(), 'sites/hello');
        });

        return $claim;
    }

    private function push(string $claim, string $secret = 'new-secret', array $data = [], ?string $signWith = null, array $over = [])
    {
        $body = $over + ['event' => 'site.connect', 'event_id' => 'e-'.uniqid(), 'brand_id' => 2, 'occurred_at' => now()->toAtomString(),
            'data' => $data + ['site_id' => 5, 'webhook_id' => 77, 'secret' => $secret, 'events' => ['stock.changed'], 'claim_id' => substr(hash('sha256', $claim), 0, 16)]];
        $raw = json_encode($body);

        return $this->call('POST', '/webhooks/stoqs', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_STOQS_EVENT' => 'site.connect',
            'HTTP_X_STOQS_SIGNATURE' => 'sha256='.hash_hmac('sha256', $raw, $signWith ?? $claim),
        ], $raw);
    }

    private function ping(string $secret)
    {
        $raw = json_encode(['event' => 'ping', 'event_id' => 'p-'.uniqid(), 'data' => []]);

        return $this->call('POST', '/webhooks/stoqs', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_STOQS_SIGNATURE' => 'sha256='.hash_hmac('sha256', $raw, $secret),
        ], $raw);
    }

    public function test_hello_sends_connect_when_there_is_no_secret_and_keeps_the_claim(): void
    {
        $claim = $this->hello();
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $claim);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'sites/hello') && $r['connect']['webhook_url'] === 'https://shop.test/webhooks/stoqs');
        $this->assertSame($claim, Setting::get('stockkeeping.connect_claim')['claim']);
        $this->assertSame('connecting', StoqsConnect::status()['state']);
        $this->assertSame($claim, $this->hello(), 'the same fresh claim goes again, so a push in flight still fits');
    }

    public function test_hello_sends_no_connect_when_connected_unless_forced_or_flagged(): void
    {
        config(['stockkeeping.webhook_secret' => self::OLD]);
        Http::fake(['stoqs.test/api/v1/sites/hello' => Http::response(['ok' => true])]);
        StoqsSites::hello();
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'sites/hello') && ! isset($r['connect']));
        $this->assertSame('connected', StoqsConnect::status()['state']);

        $this->assertNotSame('', $this->hello('pending', true));   // «اتصال دوباره»
        Setting::put('stockkeeping.connect_claim', null);
        Setting::put('stockkeeping.reconnect_needed', true);
        $this->assertNotSame('', $this->hello());                  // marked «reconnect needed»
        $this->assertNotSame('connected', StoqsConnect::status()['state']);
    }

    public function test_a_valid_push_stores_the_secret_answers_the_proof_and_works_once(): void
    {
        $claim = $this->hello();

        $res = $this->push($claim);
        $res->assertOk();
        $this->assertSame(hash_hmac('sha256', "connected\n77\n".$claim, 'new-secret'), $res->json('proof'));
        $this->assertSame('new-secret', Setting::get('stockkeeping.webhook_secret'));
        $this->assertNull(Setting::get('stockkeeping.connect_claim'));
        $this->assertSame('connected', StoqsConnect::status()['state']);
        $this->assertSame(200, $this->ping('new-secret')->getStatusCode());

        $this->push($claim, 'another-secret')->assertStatus(401);   // single use
        $this->assertSame('new-secret', Setting::get('stockkeeping.webhook_secret'));
    }

    public function test_a_forged_signature_a_wrong_claim_id_or_an_expired_claim_is_refused_and_stores_nothing(): void
    {
        $claim = $this->hello();

        $this->push($claim, 'evil', [], 'a-key-StoqS-does-not-know')->assertStatus(401);
        $this->push($claim, 'evil', ['claim_id' => substr(hash('sha256', 'other'), 0, 16)])->assertStatus(401);
        $this->push($claim, '', [])->assertStatus(401);                       // no secret in it
        $this->assertSame('', (string) config('stockkeeping.webhook_secret'));
        $this->assertNotNull(Setting::get('stockkeeping.connect_claim'), 'refusals do not use the claim up');

        Setting::put('stockkeeping.connect_claim', ['claim' => $claim, 'created' => time() - StoqsConnect::CLAIM_TTL - 1]);
        $this->push($claim, 'late')->assertStatus(401);
        $this->assertNull(Setting::get('stockkeeping.webhook_secret'));
    }

    public function test_with_no_pending_claim_a_push_is_refused_even_when_a_secret_is_set(): void
    {
        config(['stockkeeping.webhook_secret' => self::OLD]);
        $claim = bin2hex(random_bytes(32));   // never sent in a hello
        $this->push($claim, 'hijack')->assertStatus(401);
        $this->assertSame(200, $this->ping(self::OLD)->getStatusCode());
        $this->assertNull(Setting::get('stockkeeping.webhook_secret'));
    }

    public function test_the_previous_secret_works_for_an_hour_for_webhooks_and_signed_reads(): void
    {
        config(['stockkeeping.webhook_secret' => self::OLD]);
        $claim = $this->hello('pending', true);
        $this->push($claim, 'fresh-secret')->assertOk();

        $this->assertSame(200, $this->ping('fresh-secret')->getStatusCode());
        $this->assertSame(200, $this->ping(self::OLD)->getStatusCode(), 'the old secret still fits during the hour');
        $this->assertSame(401, $this->ping('a-third-secret')->getStatusCode());
        $read = function (string $secret) {
            $ts = time();
            $nonce = bin2hex(random_bytes(16));

            return $this->flushHeaders()->withHeaders(['X-Stoqs-Timestamp' => (string) $ts, 'X-Stoqs-Nonce' => $nonce,
                'X-Stoqs-Signature' => VerifyCampaignReadAccess::sign('GET', 'shop.test', '/api/campaigns/plan', '', $ts, $nonce, $secret)])
                ->getJson('/api/campaigns/plan')->getStatusCode();
        };
        $this->assertSame(200, $read('fresh-secret'));
        $this->assertSame(200, $read(self::OLD));

        Setting::put('stockkeeping.webhook_secret_prev_until', time() - 1);   // the hour is over
        $this->assertSame(401, $this->ping(self::OLD)->getStatusCode());
        $this->assertSame(401, $read(self::OLD));
        $this->assertSame(200, $read('fresh-secret'));
    }

    public function test_signed_reports_carry_a_nonce_and_sign_with_the_current_secret(): void
    {
        config(['stockkeeping.webhook_secret' => 'cur-secret']);
        Http::fake(['stoqs.test/api/v1/promotions/ack' => Http::response(['ok' => true])]);
        StoqsApi::signedCall('POST', 'promotions/ack', ['site_url' => 'https://shop.test', 'own_discounts' => ['rules' => [], 'coupons' => []]]);
        StoqsApi::signedCall('POST', 'promotions/ack', ['site_url' => 'https://shop.test']);

        $nonces = [];
        Http::assertSent(function (Request $r) use (&$nonces) {
            $nonce = (string) ($r->header('X-Stoqs-Site-Nonce')[0] ?? '');
            $ts = (int) ($r->header('X-Stoqs-Site-Timestamp')[0] ?? 0);
            $nonces[] = $nonce;

            return preg_match('/^[0-9a-f]{32}$/', $nonce) === 1
                && $r->header('X-Stoqs-Site-Signature')[0] === 'sha256='.hash_hmac('sha256', "POST\npromotions/ack\n".$ts."\n".$nonce."\n".$r->body(), 'cur-secret');
        });
        $this->assertCount(2, array_unique($nonces), 'a new nonce for every call');
    }

    public function test_a_401_signature_refusal_marks_reconnect_and_the_next_hello_connects(): void
    {
        config(['stockkeeping.webhook_secret' => self::OLD]);
        Http::fake(['stoqs.test/api/v1/promotions/ack' => Http::response(['ok' => false, 'error' => 'signature_invalid'], 401)]);
        try {
            StoqsApi::signedCall('POST', 'promotions/ack', ['site_url' => 'https://shop.test']);
            $this->fail('a refused report throws');
        } catch (\App\Services\StockKeeping\StoqsApiError $e) {
            $this->assertSame(401, $e->status);
        }
        $this->assertTrue((bool) Setting::get('stockkeeping.reconnect_needed'));
        $this->assertSame('not_connected', StoqsConnect::status()['state']);

        // a plain 401 (a wrong API key) is not a signature problem
        Setting::put('stockkeeping.reconnect_needed', false);
        Http::fake(['stoqs.test/api/v1/promotions/source' => Http::sequence()
            ->push(['error' => ['code' => 'invalid_key']], 401)->push(['error' => ['code' => 'signature_required']], 401)]);
        StoqsApi::putSource(['source' => 'shop']);
        $this->assertFalse((bool) Setting::get('stockkeeping.reconnect_needed'));
        StoqsApi::putSource(['source' => 'shop']);
        $this->assertTrue((bool) Setting::get('stockkeeping.reconnect_needed'));

        $claim = $this->hello();
        $this->assertNotSame('', $claim);
        $this->push($claim, 'renewed')->assertOk();
        $this->assertFalse((bool) Setting::get('stockkeeping.reconnect_needed'));
        $this->assertSame('connected', StoqsConnect::status()['state']);
    }

    public function test_the_admin_page_shows_the_state_and_the_button_reconnects(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get(route('admin.settings.integration'))->assertOk()->assertSee('متصل نیست')->assertSee('اتصال دوباره');

        Http::fake(['stoqs.test/api/v1/sites/hello' => Http::sequence()->push(['ok' => true, 'connect' => ['status' => 'approval_needed']])
            ->push(['ok' => true, 'connect' => ['status' => 'refused', 'reason' => 'bad_webhook_url']])]);
        $this->actingAs($admin)->post(route('admin.settings.integration.reconnect'))->assertRedirect()->assertSessionHas('success');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'sites/hello') && isset($r['connect']['claim']));
        $this->actingAs($admin)->get(route('admin.settings.integration'))->assertOk()->assertSee('منتظر تأیید در StoqS');

        config(['stockkeeping.webhook_secret' => 'now-set']);
        Setting::put('stockkeeping.connect', null);
        $this->actingAs($admin)->get(route('admin.settings.integration'))->assertOk()->assertSee('متصل');

        $this->actingAs($admin)->post(route('admin.settings.integration.reconnect'))->assertRedirect()->assertSessionHas('error');
    }
}
