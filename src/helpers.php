<?php

use Hengineer\BopomofoCaptcha\BopomofoCaptcha;

if (!function_exists('bopomofo_captcha')) {
    function bopomofo_captcha(): BopomofoCaptcha
    {
        return app('bopomofo-captcha');
    }
}

if (!function_exists('bopomofo_captcha_src')) {
    function bopomofo_captcha_src(string $config = 'default'): string
    {
        return app('bopomofo-captcha')->src($config);
    }
}

if (!function_exists('bopomofo_captcha_img')) {
    function bopomofo_captcha_img(string $config = 'default', array $attrs = []): string
    {
        return app('bopomofo-captcha')->img($config, $attrs);
    }
}

if (!function_exists('bopomofo_captcha_check')) {
    function bopomofo_captcha_check(string $value): bool
    {
        return app('bopomofo-captcha')->check($value);
    }
}
