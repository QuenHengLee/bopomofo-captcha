<?php

namespace Hengineer\BopomofoCaptcha\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static \Symfony\Component\HttpFoundation\Response|array create(string $config = 'default', bool $api = false)
 * @method static array  generate(string $config = 'default')
 * @method static bool   check(string $value)
 * @method static bool   check_api(string $value, string $key)
 * @method static string src(string $config = 'default')
 * @method static string img(string $config = 'default', array $attrs = [])
 */
class BopomofoCaptcha extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'bopomofo-captcha';
    }
}
