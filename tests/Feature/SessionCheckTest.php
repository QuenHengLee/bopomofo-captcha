<?php

namespace Hengineer\BopomofoCaptcha\Tests\Feature;

use Hengineer\BopomofoCaptcha\BopomofoCaptcha;
use Hengineer\BopomofoCaptcha\Tests\TestCase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

class SessionCheckTest extends TestCase
{
    public function test_check_returns_false_when_no_captcha_in_session(): void
    {
        $this->assertFalse(bopomofo_captcha_check('anything'));
    }

    public function test_check_accepts_correct_answer(): void
    {
        $this->seedSession('ㄅㄆㄇㄈ');

        $this->assertTrue(bopomofo_captcha_check('ㄅㄆㄇㄈ'));
    }

    public function test_check_rejects_wrong_answer(): void
    {
        $this->seedSession('ㄅㄆㄇㄈ');

        $this->assertFalse(bopomofo_captcha_check('ㄉㄊㄋㄌ'));
    }

    public function test_check_is_one_shot_on_success(): void
    {
        $this->seedSession('ㄅㄆㄇㄈ');

        $this->assertTrue(bopomofo_captcha_check('ㄅㄆㄇㄈ'));
        $this->assertFalse(bopomofo_captcha_check('ㄅㄆㄇㄈ'), 'second attempt should fail after consumption');
    }

    public function test_check_is_one_shot_on_failure(): void
    {
        $this->seedSession('ㄅㄆㄇㄈ');

        $this->assertFalse(bopomofo_captcha_check('WRONG'));
        // Wrong attempt should also consume the captcha to enforce one-shot semantics.
        $this->assertFalse(bopomofo_captcha_check('ㄅㄆㄇㄈ'), 'correct answer after wrong should also fail');
    }

    public function test_check_rejects_expired_entry(): void
    {
        session()->put('bopomofo_captcha', [
            'sensitive' => true,
            'key'       => Crypt::encrypt(Hash::make('ㄅㄆㄇㄈ')),
            'encrypt'   => true,
            'expires'   => time() - 1,
        ]);

        $this->assertFalse(bopomofo_captcha_check('ㄅㄆㄇㄈ'));
    }

    protected function seedSession(string $answer): void
    {
        /** @var BopomofoCaptcha $captcha */
        $captcha = app('bopomofo-captcha');

        // Bypass image rendering: replicate what create() persists.
        session()->put('bopomofo_captcha', [
            'sensitive' => true,
            'key'       => Crypt::encrypt(Hash::make($answer)),
            'encrypt'   => true,
            'expires'   => time() + 60,
        ]);
    }
}
