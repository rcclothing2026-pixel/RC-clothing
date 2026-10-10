<?php

return [
    // SMS is sent exclusively through Melipayamak (see config/services.php for
    // the endpoints and App\Services\Sms\SmsService for the gateway). The API key
    // and per-purpose bodyIds are managed from the admin SMS settings page and
    // stored as `sms.*` settings; env provides the fallback key.

    // OTP behaviour
    'otp_length' => 5,
    'otp_ttl_seconds' => 120,
    'otp_max_attempts' => 5,
    'otp_resend_seconds' => 60,
];
