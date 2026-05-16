<?php

namespace Hengineer\BopomofoCaptcha\Tests\Unit;

use Hengineer\BopomofoCaptcha\Tests\TestCase;

class ConfigTest extends TestCase
{
    public function test_package_config_is_merged_under_bopomofo_captcha_key(): void
    {
        $characters = config('bopomofo-captcha.characters');
        $this->assertIsArray($characters);
        $this->assertCount(37, $characters, 'Bopomofo alphabet has 37 symbols');
        $this->assertContains('ㄅ', $characters);
        $this->assertContains('ㄩ', $characters);
    }

    public function test_default_profile_exposes_render_parameters(): void
    {
        $defaults = config('bopomofo-captcha.default');
        $this->assertIsArray($defaults);
        $this->assertSame(4, $defaults['length']);
        $this->assertSame(200, $defaults['width']);
        $this->assertSame(70, $defaults['height']);
        $this->assertTrue($defaults['encrypt']);
    }

    public function test_named_profiles_exist(): void
    {
        $this->assertIsArray(config('bopomofo-captcha.flat'));
        $this->assertIsArray(config('bopomofo-captcha.mini'));
        $this->assertIsArray(config('bopomofo-captcha.inverse'));
    }
}
