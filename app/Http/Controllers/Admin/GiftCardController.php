<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GiftCard;
use App\Support\Jalali;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class GiftCardController extends Controller
{
    public function index(): View
    {
        return view('admin.gift_cards.index', ['cards' => GiftCard::latest()->paginate(20)]);
    }

    public function store(Request $request): RedirectResponse
    {
        $v = $request->validate([
            'code' => ['nullable', 'string', 'max:60', 'unique:gift_cards,code'],
            'initial_balance' => ['required', 'integer', 'min:1000'],
            'expires_at' => ['nullable', 'string'],
        ]);

        $code = $v['code'] ? mb_strtoupper(trim($v['code'])) : 'GIFT-'.Str::upper(Str::random(8));
        GiftCard::create([
            'code' => $code,
            'initial_balance' => (int) $v['initial_balance'],
            'balance' => (int) $v['initial_balance'],
            'expires_at' => Jalali::parse($v['expires_at'] ?? null),
            'is_active' => true,
        ]);

        return back()->with('success', 'کارت هدیه «'.$code.'» صادر شد.');
    }

    public function destroy(GiftCard $giftCard): RedirectResponse
    {
        $giftCard->delete();

        return back()->with('success', 'کارت هدیه حذف شد.');
    }
}
