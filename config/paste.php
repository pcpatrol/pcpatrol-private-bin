<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Maximum Payload Size
    |--------------------------------------------------------------------------
    |
    | The largest encrypted payload the server will accept, in bytes. Because
    | the payload is base64 encoded ciphertext it is roughly a third larger
    | than the text the user typed.
    |
    */

    'max_payload_bytes' => (int) env('PASTE_MAX_PAYLOAD_BYTES', 2 * 1024 * 1024),

    /*
    |--------------------------------------------------------------------------
    | Expiry Options
    |--------------------------------------------------------------------------
    |
    | The retention periods a visitor may choose from, mapped to the number of
    | seconds the paste is kept. A null value means the paste is kept until it
    | is deleted by hand.
    |
    */

    'expiry_options' => [
        '5min' => 300,
        '1hour' => 3600,
        '1day' => 86400,
        '1week' => 604800,
        '1month' => 2592000,
        '1year' => 31536000,
        'never' => null,
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Expiry
    |--------------------------------------------------------------------------
    |
    | The retention period preselected in the form. It must be one of the keys
    | configured above.
    |
    */

    'default_expiry' => env('PASTE_DEFAULT_EXPIRY', '1week'),

];
