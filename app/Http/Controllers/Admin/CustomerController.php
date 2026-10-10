<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\User;
use App\Services\Otp\OtpService;
use App\Services\StockKeeping\StockKeepingClient;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    private const PAID = [Order::STATUS_PAID, Order::STATUS_PROCESSING, Order::STATUS_SHIPPED, Order::STATUS_DELIVERED];

    public function index(Request $request): View
    {
        $customers = User::query()
            ->withCount(['orders as paid_orders_count' => fn ($q) => $q->whereIn('status', self::PAID)])
            ->withSum(['orders as total_spent' => fn ($q) => $q->whereIn('status', self::PAID)], 'total')
            ->when($request->string('q')->toString(), fn ($query, $term) => $query->where(fn ($w) => $w
                ->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")))
            ->when($request->string('filter')->toString() === 'buyers', fn ($q) => $q->having('paid_orders_count', '>', 0))
            ->when($request->string('filter')->toString() === 'admins', fn ($q) => $q->where('is_admin', true))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.customers.index', compact('customers'));
    }

    public function show(Request $request, User $customer, StockKeepingClient $stockKeeping): View
    {
        $crm = null;
        if ($customer->phone && config('stockkeeping.enabled')) {
            $crm = Cache::remember('crm:'.$customer->phone, now()->addMinutes(10),
                fn () => $stockKeeping->findCustomer($customer->phone));
        }

        return view('admin.customers.show', [
            'customer' => $customer->load(['addresses']),
            'orders' => $customer->orders()->latest()->take(20)->get(),
            'crm' => $crm,
        ]);
    }

    public function create(): View
    {
        return view('admin.customers.form', ['customer' => new User]);
    }

    public function store(Request $request): RedirectResponse
    {
        [$data, $error] = $this->validated($request, null);
        if ($error) {
            return back()->withInput()->with('error', $error);
        }

        // Admin-created accounts are trusted: mark the phone verified so the
        // person can sign in with OTP straight away.
        $user = User::create([...$data, 'phone_verified_at' => now()]);

        return redirect()->route('admin.customers.show', $user)
            ->with('success', ($user->is_admin ? 'مدیر' : 'کاربر').' «'.$user->displayName().'» ایجاد شد.');
    }

    public function edit(User $customer): View
    {
        return view('admin.customers.form', ['customer' => $customer]);
    }

    public function update(Request $request, User $customer): RedirectResponse
    {
        [$data, $error] = $this->validated($request, $customer);
        if ($error) {
            return back()->withInput()->with('error', $error);
        }

        // Never let an admin strip their OWN admin access: a demote-self save
        // locks you out of the panel with no way back short of editing the DB
        // by hand (mirrors the self-delete guard in destroy()).
        $selfDemote = $request->user()->is($customer) && $customer->is_admin && ! $data['is_admin'];
        if ($selfDemote) {
            $data['is_admin'] = true;
        }

        $customer->update($data);

        return redirect()->route('admin.customers.show', $customer)->with('success', $selfDemote
            ? 'تغییرات ذخیره شد. توجه: برای جلوگیری از قفل‌شدن، دسترسی مدیریت حساب خودتان حذف نشد.'
            : 'تغییرات ذخیره شد.');
    }

    public function destroy(Request $request, User $customer): RedirectResponse
    {
        if ($request->user()->is($customer)) {
            return back()->with('error', 'نمی‌توانید حساب خودتان را حذف کنید.');
        }

        $customer->delete();

        return redirect()->route('admin.customers.index')->with('success', 'کاربر حذف شد.');
    }

    /**
     * Validate + normalize a user payload. Returns [data, errorMessage]; the
     * phone is canonicalized to 09xxxxxxxxx (matching the OTP login flow) and
     * uniqueness is checked on that normalized value. $ignore is the user being
     * edited (null on create).
     *
     * @return array{0: array<string, mixed>|null, 1: string|null}
     */
    private function validated(Request $request, ?User $ignore): array
    {
        $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($ignore)],
            'group' => ['nullable', 'string', 'max:60'],
            'is_admin' => ['nullable', 'boolean'],
        ]);

        $phone = app(OtpService::class)->normalize((string) $request->input('phone'));
        if (! preg_match('/^09\d{9}$/', $phone)) {
            return [null, 'شماره موبایل معتبر نیست. نمونه: ۰۹۱۲۰۰۰۰۰۰۰'];
        }

        $exists = User::where('phone', $phone)
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->getKey()))
            ->exists();
        if ($exists) {
            return [null, 'این شماره موبایل قبلاً برای کاربر دیگری ثبت شده است.'];
        }

        return [[
            'name' => $request->input('name') ?: null,
            'phone' => $phone,
            'email' => $request->input('email') ?: null,
            'group' => $request->input('group') ?: null,
            'is_admin' => $request->boolean('is_admin'),
        ], null];
    }
}
