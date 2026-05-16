<?php

namespace Hengineer\BopomofoCaptcha\Tests\Feature;

use Hengineer\BopomofoCaptcha\BopomofoCaptcha;
use Hengineer\BopomofoCaptcha\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

class ApiCheckTest extends TestCase
{
    public function test_check_api_returns_false_for_unknown_key(): void
    {
        /** @var BopomofoCaptcha $captcha */
        $captcha = app('bopomofo-captcha');

        $this->assertFalse($captcha->check_api('ㄅㄆㄇㄈ', 'nonexistent_key'));
    }

    public function test_check_api_accepts_correct_answer(): void
    {
        $key = $this->seedCache('ㄅㄆㄇㄈ');

        /** @var BopomofoCaptcha $captcha */
        $captcha = app('bopomofo-captcha');

        $this->assertTrue($captcha->check_api('ㄅㄆㄇㄈ', $key));
    }

    public function test_check_api_rejects_wrong_answer(): void
    {
        $key = $this->seedCache('ㄅㄆㄇㄈ');

        /** @var BopomofoCaptcha $captcha */
        $captcha = app('bopomofo-captcha');

        $this->assertFalse($captcha->check_api('ㄉㄊㄋㄌ', $key));
    }

    public function test_check_api_is_one_shot(): void
    {
        $key = $this->seedCache('ㄅㄆㄇㄈ');

        /** @var BopomofoCaptcha $captcha */
        $captcha = app('bopomofo-captcha');

        $this->assertTrue($captcha->check_api('ㄅㄆㄇㄈ', $key));
        $this->assertFalse($captcha->check_api('ㄅㄆㄇㄈ', $key), 'key must be consumed on first attempt');
    }

    protected function seedCache(string $answer): string
    {
        $key = 'bopomofo_captcha_'.bin2hex(random_bytes(8));

        Cache::put($key, [
            'hash'      => Crypt::encrypt(Hash::make($answer)),
            'sensitive' => true,
            'encrypt'   => true,
        ], 60);

        return $key;
    }
}
