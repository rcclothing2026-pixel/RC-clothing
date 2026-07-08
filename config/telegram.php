<?php

return [
    // Bot token from @BotFather. Set it in the admin panel (stored in settings)
    // or here via env. Admin order notifications go to assigned admins; customers
    // can link their account to receive their own order updates.
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),

    // Public @username of the bot (without the @). Used to build the customer
    // "connect" deep link (t.me/<username>?start=…). Auto-detected from the
    // token via getMe when reachable; otherwise set it manually in the panel.
    'bot_username' => env('TELEGRAM_BOT_USERNAME'),

    // Iran-filtered hosts can't reach api.telegram.org. Send priority is:
    //   0) padeliran hub if set (POST /telegram-hub/send with Bearer token) —
    //      fastest + most reliable, no relay round-trip, single place to log.
    //   1) proxy (SOCKS/HTTP) if set,
    //   2) Google Apps Script relay,
    //   3) direct.
    'hub_url' => env('TELEGRAM_HUB_URL'),            // e.g. https://padeliran.ir/api/telegram-hub/send
    'relay_url' => env('TELEGRAM_RELAY_URL'),       // Apps Script /exec URL
    'relay_secret' => env('TELEGRAM_RELAY_SECRET'), // must equal SECRET in the .gs
    'proxy' => env('TELEGRAM_PROXY'),               // e.g. socks5://user:pass@host:1080
];
