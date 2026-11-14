<?php

return [

    // Paystack webhooks per source IP per minute. Generous: legitimate bursts happen, and the
    // signature check is cheap, so this mainly stops floods of garbage.
    'webhook_per_minute' => (int) env('LIMIT_WEBHOOK_PER_MINUTE', 600),

    // Money-moving browser actions (deposit start/callback, escrow actions) per user per minute.
    'money_per_minute' => (int) env('LIMIT_MONEY_PER_MINUTE', 30),

    // Anonymous page traffic per IP per minute.
    'web_per_minute' => (int) env('LIMIT_WEB_PER_MINUTE', 240),

];
