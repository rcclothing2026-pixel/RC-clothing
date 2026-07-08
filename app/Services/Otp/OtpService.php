<?php

namespace App\Services\Otp;

use App\Models\OtpCode;
use App\Models\User;
use App\Services\Sms\SmsService;
use Illuminate\Validation\ValidationException;

class OtpService
{
    public function __construct(private readonly SmsService $sms) {}

    /**
     * Generate and send an OTP for the given phone. Returns dev code when the
     * log driver is active so it can be surfaced in non-production environments.
     */
    public function send(string $phone): ?string
    {
        $phone = $this->normalize($phone);

        // Resend throttle: if a still-valid code was sent within the resend
        // window, don't send another SMS — reuse it. The caller forwards the user
        // to the code-entry screen so they enter the code they already have,
        // instead of seeing an error (which also kept the on-screen timer and the
        // real cooldown out of sync). Still prevents SMS spam: no new message goes
        // out until the window passes.
        $recent = OtpCode::where('phone', $phone)
            ->whereNull('consumed_at')
            ->where('created_at', '>', now()->subSeconds(config('sms.otp_resend_seconds')))
            ->latest()
            ->first();

        if ($recent && ! $recent->isExpired()) {
            return $this->sms->isConfigured() ? null : $recent->code;
        }

        // Our own fallback code, used by providers that send a code we supply.
        $code = (string) random_int(
            (int) str_pad('1', config('sms.otp_length'), '0'),
            (int) str_pad('9', config('sms.otp_length'), '9'),
        );

        try {
            // With an OTP bodyId configured we send our own code and get null
            // back; the auto-OTP fallback generates + returns the code instead.
            $providerCode = $this->sms->sendOtp($phone, $code);
            $this->log($phone, 'sent', null);
        } catch (\Throwable $e) {
            $this->log($phone, 'failed', $e->getMessage());
            throw $e;
        }

        $code = $providerCode ?: $code;

        OtpCode::create([
            'phone' => $phone,
            'code' => $code,
            'expires_at' => now()->addSeconds(config('sms.otp_ttl_seconds')),
        ]);

        // When SMS isn't configured (local/dev), surface the code so logins work.
        return $this->sms->isConfigured() ? null : $code;
    }

    /**
     * Verify the code and return the (created or existing) user.
     */
    public function verify(string $phone, string $code): User
    {
        $phone = $this->normalize($phone);

        $otp = OtpCode::where('phone', $phone)
            ->whereNull('consumed_at')
            ->latest()
            ->first();

        if (! $otp || $otp->isExpired()) {
            throw ValidationException::withMessages([
                'code' => 'کد منقضی شده است. کد جدید درخواست کنید.',
            ]);
        }

        if ($otp->attempts >= config('sms.otp_max_attempts')) {
            throw ValidationException::withMessages([
                'code' => 'تعداد تلاش‌ها بیش از حد مجاز است. کد جدید درخواست کنید.',
            ]);
        }

        if (! hash_equals($otp->code, trim($code))) {
            $otp->increment('attempts');
            throw ValidationException::withMessages(['code' => 'کد وارد شده نادرست است.']);
        }

        $otp->update(['consumed_at' => now()]);

        return User::firstOrCreate(
            ['phone' => $phone],
            ['phone_verified_at' => now()],
        );
    }

    /** Record an OTP send in the SMS log (best-effort). */
    private function log(string $phone, string $status, ?string $error): void
    {
        try {
            \App\Models\SmsMessage::create([
                'phone' => $phone, 'body' => 'کد ورود یک‌بارمصرف', 'type' => 'otp', 'status' => $status, 'error' => $error,
            ]);
        } catch (\Throwable) {
            // log table not migrated yet — ignore
        }
    }

    /** Normalize Iranian mobile numbers to a canonical 09xxxxxxxxx form. */
    public function normalize(string $phone): string
    {
        // Convert Persian/Arabic digits to Latin.
        $phone = strtr($phone, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
        $phone = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($phone, '0098')) {
            $phone = '0'.substr($phone, 4);
        } elseif (str_starts_with($phone, '98') && strlen($phone) === 12) {
            $phone = '0'.substr($phone, 2);
        } elseif (str_starts_with($phone, '9') && strlen($phone) === 10) {
            $phone = '0'.$phone;
        }

        return $phone;
    }
}
