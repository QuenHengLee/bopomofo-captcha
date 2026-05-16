{{--
    Bopomofo Captcha demo page.

    Drop this file at: resources/views/bopomofo-captcha-demo.blade.php
    Then register the two routes from examples/routes.php in your routes/web.php.
    Visit /bopomofo-captcha-demo in the browser.
--}}
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Bopomofo Captcha Demo · 注音驗證碼示範</title>
    <style>
        body { font-family: system-ui, "PingFang TC", "Microsoft JhengHei", sans-serif; max-width: 480px; margin: 4rem auto; padding: 0 1rem; }
        h1 { font-size: 1.4rem; }
        .row { display: flex; align-items: center; gap: .75rem; margin: 1rem 0; }
        img.captcha { border: 1px solid #ddd; border-radius: 6px; }
        button.reload { background: none; border: 1px solid #bbb; padding: .35rem .7rem; border-radius: 6px; cursor: pointer; }
        button.reload:hover { background: #f3f3f3; }
        label { display: block; margin: .75rem 0 .25rem; }
        input[name="captcha"] { width: 100%; padding: .55rem .7rem; font-size: 1.4rem; letter-spacing: .25rem; box-sizing: border-box; }
        button.submit { margin-top: 1rem; padding: .55rem 1.2rem; font-size: 1rem; background: #2563eb; color: white; border: 0; border-radius: 6px; cursor: pointer; }
        .ok  { color: #0a7f3f; }
        .err { color: #c0392b; }
        small { color: #666; }
    </style>
</head>
<body>
    <h1>Bopomofo Captcha 示範</h1>
    <p><small>請用注音輸入法（Zhuyin IME）把下方圖片中的符號打進輸入框。</small></p>

    <form method="POST" action="{{ route('bopomofo-captcha-demo.submit') }}">
        @csrf

        <div class="row">
            <img id="captcha-img" class="captcha" src="{{ bopomofo_captcha_src() }}" alt="captcha">
            <button type="button" class="reload" onclick="reloadCaptcha()">↻ 換一張</button>
        </div>

        <label for="captcha">請輸入上方注音符號：</label>
        <input id="captcha" name="captcha" autofocus autocomplete="off" inputmode="text" placeholder="例如：ㄅㄆㄇㄈ">

        @error('captcha')
            <p class="err">✗ {{ $message }}</p>
        @enderror

        @if (session('status'))
            <p class="ok">✓ {{ session('status') }}</p>
        @endif

        <button type="submit" class="submit">送出</button>
    </form>

    <script>
        function reloadCaptcha() {
            const img = document.getElementById('captcha-img');
            const url = new URL("{{ route('bopomofo-captcha') }}", window.location.origin);
            url.searchParams.set('_', Date.now());
            img.src = url.toString();
            document.getElementById('captcha').value = '';
            document.getElementById('captcha').focus();
        }
    </script>
</body>
</html>
