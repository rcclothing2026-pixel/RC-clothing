<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Otp\OtpService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class OtpLoginController extends Controller
{
    public function __construct(private readonly OtpService $otp) {}

    public function show(): View
    {
        return view('auth.login');
    }

    /** Step 1: receive phone, send the OTP. */
    public function sendOtp(Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'regex:/^[0-9۰-۹٠-٩+\s-]{10,15}$/'],
        ], [], ['phone' => 'شماره موبایل']);

        $phone = $this->otp->normalize($data['phone']);

        if (! preg_match('/^09\d{9}$/', $phone)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'شماره موبایل معتبر نیست.'], 422);
            }
            return back()->withErrors(['phone' => 'شماره موبایل معتبر نیست.'])->withInput();
        }

        $devCode = $this->otp->send($phone);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'dev_code' => $devCode]);
        }

        return redirect()->route('login.verify.show', ['phone' => $phone])
            ->with('status', 'کد تایید ارسال شد.')
            ->with('dev_code', $devCode);
    }

    /** Step 2: show the code entry form. */
    public function showVerify(Request $request): View|RedirectResponse
    {
        $phone = $this->otp->normalize((string) $request->query('phone'));
        if (! preg_match('/^09\d{9}$/', $phone)) {
            return redirect()->route('login');
        }

        return view('auth.verify', ['phone' => $phone]);
    }

    /** Step 3: verify the code and log the user in. */
    public function verify(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'code' => ['required', 'string'],
        ]);

        $user = $this->otp->verify($data['phone'], $data['code']);

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        // Brand-new (or nameless) account → collect name+surname first; the
        // intended URL stays in the session for redirect()->intended() afterwards.
        if (blank($user->name)) {
            return redirect()->route('profile.complete');
        }

        return redirect()->intended(route('account.index'))->with('success', 'خوش آمدید!');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
