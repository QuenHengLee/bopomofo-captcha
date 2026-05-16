<?php

namespace Hengineer\BopomofoCaptcha;

use Illuminate\Hashing\BcryptHasher;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;

class BopomofoCaptchaServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/bopomofo-captcha.php' => config_path('bopomofo-captcha.php'),
        ], 'bopomofo-captcha-config');

        $this->publishes([
            __DIR__ . '/../assets' => public_path('vendor/bopomofo-captcha'),
        ], 'bopomofo-captcha-assets');

        $this->registerRoutes();
        $this->registerValidators();
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/bopomofo-captcha.php', 'bopomofo-captcha');

        $this->app->singleton('bopomofo-captcha', function ($app) {
            return new BopomofoCaptcha(
                $app['files'],
                $app['config'],
                new ImageManager(new GdDriver()),
                $app['session.store'],
                new BcryptHasher(),
                new Str()
            );
        });

        $this->app->alias('bopomofo-captcha', BopomofoCaptcha::class);
    }

    protected function registerRoutes(): void
    {
        $throttle = config('bopomofo-captcha.throttle', '60,1');

        // Stateless / API route — no session needed (state lives in Cache).
        Route::middleware("throttle:$throttle")->group(function () {
            Route::get('bopomofo-captcha/api/{config?}', function (string $config = 'default') {
                return response()->json(app('bopomofo-captcha')->create($config, true));
            })->name('bopomofo-captcha.api');
        });

        // Session route — must run through the `web` middleware group so
        // StartSession (un)serializes the session file at request boundaries.
        // Without this, $session->put() writes to an in-memory store that
        // never gets flushed.
        Route::middleware(['web', "throttle:$throttle"])->group(function () {
            Route::get('bopomofo-captcha/{config?}', function (string $config = 'default') {
                return app('bopomofo-captcha')->create($config);
            })->name('bopomofo-captcha');
        });
    }

    protected function registerValidators(): void
    {
        Validator::extend('bopomofo_captcha', function ($attribute, $value) {
            return app('bopomofo-captcha')->check((string) $value);
        }, 'The :attribute is invalid.');

        Validator::extend('bopomofo_captcha_api', function ($attribute, $value, $parameters) {
            $key = $parameters[0] ?? '';
            return app('bopomofo-captcha')->check_api((string) $value, (string) $key);
        }, 'The :attribute is invalid.');
    }

    public function provides(): array
    {
        return ['bopomofo-captcha', BopomofoCaptcha::class];
    }
}
