<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Route throttle
    |--------------------------------------------------------------------------
    |
    | Applied to both the session-mode and stateless-mode captcha routes via
    | Laravel's throttle middleware. Format is "<max>,<perMinutes>". Bcrypt
    | hashing is CPU-heavy, so leaving this unbounded invites DoS.
    |
    */
    'throttle' => '60,1',

    /*
    |--------------------------------------------------------------------------
    | Character pool
    |--------------------------------------------------------------------------
    |
    | The 37 standard Bopomofo (Zhuyin / 注音符號) symbols:
    |   21 聲母  ㄅ ㄆ ㄇ ㄈ ㄉ ㄊ ㄋ ㄌ ㄍ ㄎ ㄏ ㄐ ㄑ ㄒ ㄓ ㄔ ㄕ ㄖ ㄗ ㄘ ㄙ
    |   16 韻母  ㄚ ㄛ ㄜ ㄝ ㄞ ㄟ ㄠ ㄡ ㄢ ㄣ ㄤ ㄥ ㄦ ㄧ ㄨ ㄩ
    |
    | These are intentionally provided as an array of UTF-8 strings (each one
    | is a single multi-byte glyph) so we never have to split with substr().
    |
    */
    'characters' => [
        'ㄅ', 'ㄆ', 'ㄇ', 'ㄈ', 'ㄉ', 'ㄊ', 'ㄋ', 'ㄌ', 'ㄍ', 'ㄎ', 'ㄏ',
        'ㄐ', 'ㄑ', 'ㄒ', 'ㄓ', 'ㄔ', 'ㄕ', 'ㄖ', 'ㄗ', 'ㄘ', 'ㄙ',
        'ㄚ', 'ㄛ', 'ㄜ', 'ㄝ', 'ㄞ', 'ㄟ', 'ㄠ', 'ㄡ', 'ㄢ', 'ㄣ',
        'ㄤ', 'ㄥ', 'ㄦ', 'ㄧ', 'ㄨ', 'ㄩ',
    ],

    /*
    |--------------------------------------------------------------------------
    | Captcha profiles
    |--------------------------------------------------------------------------
    |
    | Each profile is a complete render configuration. Choose one when calling
    | captcha()->create('profile') or in the validator. Profiles inherit from
    | 'default' via array_merge in the BopomofoCaptcha class.
    |
    */
    'default' => [
        'length'      => 4,        // bopomofo glyphs are wide — 4 is plenty
        'width'       => 200,
        'height'      => 70,
        'quality'     => 90,
        'angle'       => 12,       // max ± rotation degrees per glyph
        'lines'       => 4,
        'bgImage'     => true,
        'bgColor'     => '#ffffff',
        'fontColors'  => [],       // empty = random dark color per glyph
        'contrast'    => 0,
        'sharpen'     => 0,
        'blur'        => 0,
        'invert'      => false,
        'sensitive'   => true,     // bopomofo has no case; left for symmetry
        'expire'      => 60,
        'encrypt'     => true,
    ],

    'flat' => [
        'length'    => 4,
        'angle'     => 0,
        'lines'     => 1,
        'bgImage'   => false,
        'bgColor'   => '#f7f7f7',
        'sharpen'   => 5,
    ],

    'mini' => [
        'length' => 3,
        'width'  => 140,
        'height' => 56,
    ],

    'inverse' => [
        'length'   => 4,
        'bgColor'  => '#000000',
        'invert'   => true,
    ],
];
