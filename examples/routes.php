<?php

/*
|--------------------------------------------------------------------------
| Bopomofo Captcha demo routes
|--------------------------------------------------------------------------
|
| Copy these two routes into your application's routes/web.php (or
| `require __DIR__ . '/../vendor/hengineer/bopomofo-captcha/examples/routes.php';`
| if you want to mount them as-is for a quick test).
|
| The package itself already registers:
|   GET /bopomofo-captcha/{config?}        – returns the PNG (session mode)
|   GET /bopomofo-captcha/api/{config?}    – returns JSON { key, img }
|
| You don't need to redefine those — only the demo view and the form
| submission handler shown below.
|
*/

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/bopomofo-captcha-demo', function () {
    return view('bopomofo-captcha-demo');
})->name('bopomofo-captcha-demo');

Route::post('/bopomofo-captcha-demo', function (Request $request) {
    $request->validate([
        'captcha' => ['required', 'string', 'bopomofo_captcha'],
    ], [
        'captcha.required'       => '請輸入注音符號',
        'captcha.bopomofo_captcha' => '注音符號錯誤，請重新輸入',
    ]);

    return redirect()
        ->route('bopomofo-captcha-demo')
        ->with('status', '驗證成功！Captcha verified.');
})->name('bopomofo-captcha-demo.submit');
