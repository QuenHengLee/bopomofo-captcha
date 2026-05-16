<?php

namespace Hengineer\BopomofoCaptcha\Tests\Feature;

use Hengineer\BopomofoCaptcha\Tests\TestCase;
use Illuminate\Support\Facades\Route;

class RouteRegistrationTest extends TestCase
{
    public function test_session_route_is_registered_with_name(): void
    {
        $this->assertNotNull(
            Route::getRoutes()->getByName('bopomofo-captcha'),
            "expected route name 'bopomofo-captcha' to be registered"
        );
    }

    public function test_api_route_is_registered_with_name(): void
    {
        $this->assertNotNull(
            Route::getRoutes()->getByName('bopomofo-captcha.api'),
            "expected route name 'bopomofo-captcha.api' to be registered"
        );
    }

    public function test_validator_extensions_are_registered(): void
    {
        $validator = app('validator');
        $extensions = $validator->make([], [])::class;

        // Both rules should resolve without throwing.
        $this->assertTrue(
            validator(['c' => 'x'], ['c' => 'bopomofo_captcha'])->fails(),
            'bopomofo_captcha rule should exist and fail on missing session entry'
        );
        $this->assertTrue(
            validator(['c' => 'x'], ['c' => 'bopomofo_captcha_api:none'])->fails(),
            'bopomofo_captcha_api rule should exist and fail on missing cache entry'
        );
    }
}
