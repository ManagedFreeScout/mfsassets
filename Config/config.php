<?php
// MFS Assets (card freescout-modules #426). Licence checks go through the Managed FreeScout hub,
// never straight to invAIse (same as the other MFS modules).
return [
    'hub_url'   => env('MFSASSETS_HUB_URL', 'https://app.managedfreescout.com'),
    'buy_url'   => env('MFSASSETS_BUY_URL', 'https://managedfreescout.com/mfsassets/'),
    'terms_url' => 'https://managedfreescout.com/terms-and-conditions/',

    // €49/month per installation (card #426). Only for development can
    // MFSASSETS_ENFORCE_LICENSE=false switch the check off.
    'enforce_license' => env('MFSASSETS_ENFORCE_LICENSE', true),

    // Asset tags: prefix + running number (AST-0001). Admins change the prefix in the settings.
    'tag_prefix' => 'AST-',
    'tag_digits' => 4,

    // Report form behind a QR label: at most this many reports per IP address per hour.
    'report_rate_limit' => 5,
];
