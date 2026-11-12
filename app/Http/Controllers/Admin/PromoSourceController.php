<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\StockKeeping\StoqsApi;
use App\Services\StockKeeping\StoqsPromoSource;
use App\Services\StockKeeping\StoqsPromotions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

/**
 * The site manager switch, from the shop's admin: «مدیریت با فروشگاه» ↔ «مدیریت با StoqS».
 * Click → PUT /promotions/source with dry_run (StoqS answers what the switch would stop and
 * start) → a confirmation page → the same call with confirm:true. The shop flips only after
 * StoqS answers ok; a stale seq (409) refreshes the stored state instead.
 */
class PromoSourceController extends Controller
{
    private const BACK = 'admin.settings.integration';

    /** Step 1: dry run, then the confirmation page with what stops and starts. */
    public function prepare(Request $request): View|RedirectResponse
    {
        $v = $request->validate(['to' => ['required', 'in:stoqs,shop'], 'note' => ['nullable', 'string', 'max:200']]);
        $guard = $this->guard($v['to'], (string) ($v['note'] ?? ''));
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }

        return $this->confirmPage($v['to'], (string) ($v['note'] ?? ''), $guard);
    }

    /** Step 2: the ticks are required here too; then the real call, and the flip once StoqS says ok. */
    public function confirm(Request $request): View|RedirectResponse
    {
        $v = $request->validate([
            'to' => ['required', 'in:stoqs,shop'], 'note' => ['nullable', 'string', 'max:200'],
            'confirm' => ['nullable', 'boolean'], 'confirm_stop_all' => ['nullable', 'boolean'],
        ]);
        $note = (string) ($v['note'] ?? '');
        $confirm = $request->boolean('confirm');
        $stopAll = $request->boolean('confirm_stop_all');
        if (! $confirm) {   // nothing is sent to StoqS without the tick
            $guard = $this->guard($v['to'], $note);

            return $guard instanceof RedirectResponse ? $guard
                : $this->confirmPage($v['to'], $note, $guard, 'برای تغییر باید تأیید را تیک بزنید.');
        }
        $guard = $this->guard($v['to'], $note);   // the guard as StoqS sees it now (also catches a stale seq)
        if ($guard instanceof RedirectResponse) {
            return $guard;
        }
        if (! empty($guard['stop_all']) && ! $stopAll) {
            return $this->confirmPage($v['to'], $note, $guard, 'برای ادامه باید تیک «'.StoqsPromoSource::STOP_ALL.'» را بزنید.');
        }
        $state = StoqsPromoSource::state();
        try {
            $res = StoqsApi::putSource($this->body($v['to'], $note) + ['confirm' => true] + ($stopAll ? ['confirm_stop_all' => true] : []));
        } catch (\Throwable $e) {
            return back()->with('error', 'ارتباط با StoqS برقرار نشد؛ تغییری انجام نشد: '.$e->getMessage());
        }
        $j = $res['json'];
        if ($res['status'] === 409) {
            return $this->stale($j);
        }
        if ($res['status'] === 422) {
            return $this->confirmPage($v['to'], $note, (array) ($j['guard'] ?? $guard), 'StoqS تغییر را نپذیرفت: '.((string) ($j['error'] ?? '')));
        }
        if ($res['status'] !== 200 || empty($j['ok'])) {
            return redirect()->route(self::BACK)->with('error', 'StoqS تغییر را نپذیرفت (HTTP '.$res['status'].'). تغییری انجام نشد.');
        }
        if (! empty($j['unchanged'])) {
            StoqsPromoSource::adopt($j);

            return redirect()->route(self::BACK)->with('success', 'وضعیت در StoqS همین بود؛ تغییری لازم نبود.');
        }
        $seq = (int) ($j['seq'] ?? $state['seq'] + 1);
        $by = (string) ($request->user()?->name ?? '');
        StoqsPromoSource::apply((string) ($j['source'] ?? $v['to']), $seq, 'shop_admin', (string) ($j['by'] ?? $by) ?: $by,
            $note !== '' ? $note : ($j['note'] ?? null), $j['changed_at'] ?? null, false);

        return redirect()->route(self::BACK)->with('success', $v['to'] === 'stoqs'
            ? 'از این پس تخفیف‌های سایت با StoqS مدیریت می‌شود.' : 'از این پس تخفیف‌های سایت با خود فروشگاه مدیریت می‌شود.');
    }

    public function history(): View
    {
        return view('admin.settings.promo-source-history', ['rows' => StoqsPromoSource::history(), 'state' => StoqsPromoSource::state()]);
    }

    /** The dry run: the guard, or a redirect when StoqS disagrees (stale) or refuses. */
    private function guard(string $to, string $note): array|RedirectResponse
    {
        if (StoqsPromoSource::state()['source'] === $to) {
            return redirect()->route(self::BACK)->with('success', 'سایت همین الان با '.($to === 'stoqs' ? 'StoqS' : 'فروشگاه').' مدیریت می‌شود.');
        }
        try {
            $res = StoqsApi::putSource($this->body($to, $note) + ['dry_run' => true]);
        } catch (\Throwable $e) {
            return redirect()->route(self::BACK)->with('error', 'ارتباط با StoqS برقرار نشد: '.$e->getMessage());
        }
        if ($res['status'] === 409) {
            return $this->stale($res['json']);
        }
        if ($res['status'] !== 200 || empty($res['json']['ok'])) {
            return redirect()->route(self::BACK)->with('error', 'StoqS بررسی را نپذیرفت (HTTP '.$res['status'].').');
        }

        return (array) ($res['json']['guard'] ?? []);
    }

    private function body(string $to, string $note): array
    {
        return array_filter([
            'source' => $to, 'seq' => StoqsPromoSource::state()['seq'],
            'note' => $note !== '' ? $note : null, 'by' => (string) (request()->user()?->name ?? '') ?: null,
            'own_discounts' => StoqsPromoSource::ownDiscounts(),
        ], fn ($x) => $x !== null);
    }

    /** 409: StoqS's state is ahead; take it and say so. */
    private function stale(array $j): RedirectResponse
    {
        StoqsPromoSource::adopt($j);
        StoqsPromotions::forget();
        $now = StoqsPromoSource::state()['source'] === 'stoqs' ? 'مدیریت با StoqS' : 'مدیریت با فروشگاه';

        return redirect()->route(self::BACK)->with('error', 'وضعیت در StoqS تغییر کرده است؛ وضعیت فعلی: '.$now.'.');
    }

    private function confirmPage(string $to, string $note, array $guard, ?string $error = null): View
    {
        return view('admin.settings.promo-source-confirm', [
            'to' => $to, 'note' => $note, 'guard' => $guard, 'state' => StoqsPromoSource::state(), 'error' => $error,
        ]);
    }
}
