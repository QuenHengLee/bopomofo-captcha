<?php

namespace Hengineer\BopomofoCaptcha\Tests\Feature;

use Hengineer\BopomofoCaptcha\BopomofoCaptcha;
use Hengineer\BopomofoCaptcha\Tests\TestCase;

class GenerateTest extends TestCase
{
    public function test_generate_returns_answer_made_of_bopomofo_glyphs(): void
    {
        /** @var BopomofoCaptcha $captcha */
        $captcha = app('bopomofo-captcha');

        $result = $captcha->generate('default');

        $this->assertArrayHasKey('value', $result);
        $this->assertArrayHasKey('key', $result);
        $this->assertArrayHasKey('sensitive', $result);

        $answer = $result['value'];
        $glyphs = mb_str_split($answer);
        $this->assertCount(4, $glyphs, 'default profile has length=4');

        $pool = config('bopomofo-captcha.characters');
        foreach ($glyphs as $glyph) {
            $this->assertContains($glyph, $pool, "glyph '$glyph' should come from the configured pool");
        }
    }

    public function test_generate_respects_profile_length(): void
    {
        /** @var BopomofoCaptcha $captcha */
        $captcha = app('bopomofo-captcha');

        $result = $captcha->generate('mini');

        $this->assertCount(3, mb_str_split($result['value']), 'mini profile has length=3');
    }

    public function test_consecutive_generations_produce_different_answers(): void
    {
        /** @var BopomofoCaptcha $captcha */
        $captcha = app('bopomofo-captcha');

        $a = $captcha->generate('default')['value'];
        $b = $captcha->generate('default')['value'];

        // 37^4 ≈ 1.87M combinations, collisions are vanishingly rare.
        $this->assertNotSame($a, $b);
    }
}
