<?php

namespace Hengineer\BopomofoCaptcha;

use Exception;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Hashing\BcryptHasher;
use Illuminate\Session\Store as Session;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Typography\FontFactory;
use Symfony\Component\HttpFoundation\Response;

class BopomofoCaptcha
{
    protected Filesystem $files;
    protected Repository $config;
    protected ImageManager $imageManager;
    protected Session $session;
    protected Hasher $hasher;
    protected Str $str;

    protected string $assetsDir;
    protected array $fonts = [];
    protected array $backgrounds = [];

    /** Dummy bcrypt hash used to equalise timing of the unknown-key path. */
    protected string $dummyHash;

    /** Render config (resolved from a profile name). */
    protected array $characters = [];
    protected int $length = 4;
    protected int $width = 200;
    protected int $height = 70;
    protected int $quality = 90;
    protected int $angle = 12;
    protected int $lines = 4;
    protected bool $bgImage = true;
    protected string $bgColor = '#ffffff';
    protected array $fontColors = [];
    protected int $contrast = 0;
    protected int $sharpen = 0;
    protected int $blur = 0;
    protected bool $invert = false;
    protected bool $sensitive = true;
    protected int $expire = 60;
    protected bool $encrypt = true;

    protected ?ImageInterface $canvas = null;

    public function __construct(
        Filesystem $files,
        Repository $config,
        ImageManager $imageManager,
        Session $session,
        BcryptHasher $hasher,
        Str $str
    ) {
        $this->files        = $files;
        $this->config       = $config;
        $this->imageManager = $imageManager;
        $this->session      = $session;
        $this->hasher       = $hasher;
        $this->str          = $str;

        $this->assetsDir   = __DIR__ . '/../assets/';
        $this->characters  = (array) $this->config->get('bopomofo-captcha.characters');
        $this->fonts       = $this->discoverAssets('fonts', ['ttf', 'otf']);
        $this->backgrounds = $this->discoverAssets('backgrounds', ['png', 'jpg', 'jpeg', 'gif']);
        $this->dummyHash   = $this->hasher->make('bopomofo-captcha-dummy');
    }

    /**
     * Apply a named profile (default, flat, mini, inverse, …) on top of 'default'.
     */
    protected function configure(string $profile): void
    {
        $base    = (array) $this->config->get('bopomofo-captcha.default', []);
        $chosen  = $profile === 'default' ? [] : (array) $this->config->get("bopomofo-captcha.$profile", []);
        $settings = array_merge($base, $chosen);

        foreach ($settings as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }

    /**
     * Apply profile then produce a fresh answer and its persisted hash.
     */
    public function generate(string $config = 'default'): array
    {
        $this->configure($config);

        return $this->produceAnswer();
    }

    /** Assumes configure() has already run. */
    protected function produceAnswer(): array
    {
        if (empty($this->characters)) {
            throw new Exception('Bopomofo character pool is empty — check config/bopomofo-captcha.php.');
        }

        $answer = '';
        $pool   = $this->characters;
        $poolN  = count($pool);
        for ($i = 0; $i < $this->length; $i++) {
            $answer .= $pool[random_int(0, $poolN - 1)];
        }

        $key  = $this->hasher->make($this->sensitive ? $answer : $this->mb_lower($answer));
        $key  = $this->encrypt ? Crypt::encrypt($key) : $key;

        return [
            'sensitive' => $this->sensitive,
            'key'       => $key,
            'value'     => $answer,
        ];
    }

    /**
     * Render the captcha image. Returns a PNG Response, or a JSON-ready array in API mode.
     */
    public function create(string $config = 'default', bool $api = false): Response|array
    {
        $this->configure($config);

        if (empty($this->fonts)) {
            throw new Exception(
                'No font files found in assets/fonts/. Drop a CJK TTF/OTF font that includes ' .
                'Bopomofo glyphs (U+3105–U+3129), e.g. Noto Sans TC.'
            );
        }

        $generated = $this->produceAnswer();
        $answer    = $generated['value'];

        $this->canvas = $this->bgImage && !empty($this->backgrounds)
            ? $this->imageManager->read($this->backgrounds[array_rand($this->backgrounds)])
                ->resize($this->width, $this->height)
            : $this->imageManager->create($this->width, $this->height)->fill($this->bgColor);

        if ($this->contrast !== 0) {
            $this->canvas->contrast($this->contrast);
        }

        $this->drawText($answer);

        if ($this->lines > 0) {
            $this->drawLines();
        }

        if ($this->sharpen > 0) {
            $this->canvas->sharpen($this->sharpen);
        }
        if ($this->blur > 0) {
            $this->canvas->blur($this->blur);
        }
        if ($this->invert) {
            $this->canvas->invert();
        }

        $png = (string) $this->canvas->toPng();

        // Persist the answer.
        if ($api) {
            $cacheKey = 'bopomofo_captcha_' . Str::random(16);
            Cache::put($cacheKey, [
                'hash'      => $generated['key'],
                'sensitive' => $this->sensitive,
                'encrypt'   => $this->encrypt,
            ], $this->expire);

            return [
                'key' => $cacheKey,
                'img' => 'data:image/png;base64,' . base64_encode($png),
            ];
        }

        $this->session->put('bopomofo_captcha', [
            'sensitive' => $this->sensitive,
            'key'       => $generated['key'],
            'encrypt'   => $this->encrypt,
            'expires'   => time() + $this->expire,
        ]);

        return new Response($png, 200, [
            'Content-Type'        => 'image/png',
            'Cache-Control'       => 'no-cache, no-store, must-revalidate',
            'Pragma'              => 'no-cache',
            'Expires'             => '0',
        ]);
    }

    /**
     * Validate session-based answer.
     */
    public function check(string $value): bool
    {
        if (!$this->session->has('bopomofo_captcha')) {
            return false;
        }

        $stored = $this->session->get('bopomofo_captcha');

        if (isset($stored['expires']) && time() > $stored['expires']) {
            $this->session->forget('bopomofo_captcha');
            return false;
        }

        $hash  = $stored['encrypt'] ? Crypt::decrypt($stored['key']) : $stored['key'];
        $input = $stored['sensitive'] ? $value : $this->mb_lower($value);

        // One-shot: consume the captcha regardless of outcome so a wrong
        // answer cannot be retried against the same image.
        $this->session->forget('bopomofo_captcha');

        return $this->hasher->check($input, $hash);
    }

    /**
     * Validate stateless / API answer.
     */
    public function check_api(string $value, string $key): bool
    {
        $stored = Cache::pull($key);

        if (!$stored) {
            // Burn an equivalent bcrypt verification so the "unknown key" path
            // doesn't return measurably faster than the "wrong answer" path.
            $this->hasher->check($value, $this->dummyHash);
            return false;
        }

        $hash  = $stored['encrypt'] ? Crypt::decrypt($stored['hash']) : $stored['hash'];
        $input = $stored['sensitive'] ? $value : $this->mb_lower($value);

        return $this->hasher->check($input, $hash);
    }

    public function src(string $config = 'default'): string
    {
        return route('bopomofo-captcha', ['config' => $config]) . '?' . Str::random(8);
    }

    public function img(string $config = 'default', array $attrs = []): string
    {
        $attrs = array_merge(['alt' => 'captcha'], $attrs);
        $attrs['src'] = $this->src($config);

        $rendered = '';
        foreach ($attrs as $k => $v) {
            $rendered .= ' ' . $k . '="' . htmlspecialchars((string) $v, ENT_QUOTES) . '"';
        }
        return '<img' . $rendered . ' />';
    }

    public function setFonts(array $fonts): self
    {
        $this->fonts = $fonts;
        return $this;
    }

    public function setBackgrounds(array $backgrounds): self
    {
        $this->backgrounds = $backgrounds;
        return $this;
    }

    /* ------------------------------------------------------------------ */
    /* Internals                                                            */
    /* ------------------------------------------------------------------ */

    protected function drawText(string $answer): void
    {
        $glyphs = mb_str_split($answer);
        $count  = count($glyphs);
        $slot   = $this->width / ($count + 1);

        foreach ($glyphs as $i => $glyph) {
            $font    = $this->fonts[array_rand($this->fonts)];
            $size    = (int) round($this->height * 0.55);
            $color   = !empty($this->fontColors)
                ? $this->fontColors[array_rand($this->fontColors)]
                : $this->randomDarkColor();
            $angle   = $this->angle === 0 ? 0 : random_int(-$this->angle, $this->angle);
            $x       = (int) round($slot * ($i + 1));
            $y       = (int) round($this->height / 2);

            $this->canvas->text(
                $glyph,
                $x,
                $y,
                function (FontFactory $f) use ($font, $size, $color, $angle) {
                    $f->filename($font);
                    $f->size($size);
                    $f->color($color);
                    $f->angle($angle);
                    $f->align('center');
                    $f->valign('middle');
                }
            );
        }
    }

    protected function drawLines(): void
    {
        for ($i = 0; $i < $this->lines; $i++) {
            $color = $this->randomDarkColor();
            $this->canvas->drawLine(function ($line) use ($color) {
                $line->from(random_int(0, $this->width), random_int(0, $this->height));
                $line->to(random_int(0, $this->width), random_int(0, $this->height));
                $line->color($color);
                $line->width(1);
            });
        }
    }

    protected function randomDarkColor(): string
    {
        return sprintf('#%02x%02x%02x', random_int(0, 120), random_int(0, 120), random_int(0, 120));
    }

    protected function mb_lower(string $s): string
    {
        return mb_strtolower($s, 'UTF-8');
    }

    protected function discoverAssets(string $folder, array $extensions): array
    {
        $dir = $this->assetsDir . $folder;
        if (!$this->files->isDirectory($dir)) {
            return [];
        }

        $out = [];
        foreach ($this->files->files($dir) as $file) {
            if (in_array(strtolower($file->getExtension()), $extensions, true)) {
                $out[] = $file->getPathname();
            }
        }
        return $out;
    }
}
