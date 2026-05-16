# Bopomofo Captcha · API Reference

This document is for **third-party developers / frontend engineers** who need to
consume the captcha from another app (SPA, mobile client, or any cross-origin
caller). For installation and Blade-side usage, see [README.md](../README.md).

本文件給 **第三方開發者 / 前端工程師** 閱讀，說明如何從別的應用程式（SPA、行動端、跨網域呼叫端）串接此驗證碼。安裝及 Blade 端使用方式請見 [README.md](../README.md)。

---

## English

### Endpoint summary

| Method | URL | Mode | State storage | Returns |
|--------|-----|------|---------------|---------|
| `GET` | `/bopomofo-captcha/{config?}` | Session | Laravel session | `image/png` |
| `GET` | `/bopomofo-captcha/api/{config?}` | Stateless | Laravel cache (keyed) | `application/json` |

`{config}` is an optional profile name defined in `config/bopomofo-captcha.php`. Defaults to `default`. Built-in profiles: `default`, `flat`, `mini`, `inverse`.

---

### Which mode should I use?

A 3-question gate. If you answer **yes** to any of these, use **stateless mode**:

1. **Different origin?** Your frontend lives on a domain that doesn't share a cookie with the Laravel app (typical SPA on Vercel/Netlify hitting a Laravel API, or a Flutter/React Native app).
2. **No session cookie?** You're using token auth (Sanctum personal access tokens, Passport, JWT) instead of session cookies — your client doesn't carry a `laravel_session` cookie.
3. **Multiple captchas on one page?** You need two independent captchas (e.g. a signup form *and* a newsletter form on the same screen). Session mode only holds **one** answer per session — the second image overwrites the first.

Otherwise (server-rendered Blade form, same-origin, single captcha) use **session mode** — less wiring, the cookie is invisible to your code.

### Side-by-side

| Aspect | Session mode | Stateless / API mode |
|--------|--------------|----------------------|
| Endpoint | `GET /bopomofo-captcha/{config?}` | `GET /bopomofo-captcha/api/{config?}` |
| Returns | `image/png` body | `{ "key": "...", "img": "data:..." }` JSON |
| Where the hashed answer lives | Laravel session (per the app's `SESSION_DRIVER`) | Laravel cache, keyed by the returned `key` |
| What the client must carry | The session cookie — automatic in browsers, same-origin only | The `key` string — you store it in a hidden field / JS state |
| Cross-origin (CORS) | ✗ Cookies usually blocked | ✓ Works fine with CORS |
| Mobile / native client | Awkward (cookie jar required) | Natural |
| Multiple captchas per page | ✗ Only the latest counts | ✓ Each `key` is its own slot |
| Image transport size | Compact (URL only) | ~33% larger (base64 inflation) |
| Single-use? | Yes — cleared on successful check | Yes — cleared on **any** check (success or failure) |
| Expiry | `expire` seconds (default 60s) | `expire` seconds (default 60s) |
| CSRF on submit | Needed (it's a `POST` to your app) | Needed if your endpoint is session-protected; not needed for token-auth APIs |

### Anatomy of a successful round-trip

**Session mode — what each side actually does:**

1. Browser requests `/bopomofo-captcha`. The package picks 4 random glyphs (e.g. `ㄅㄆㄇㄈ`), bcrypt-hashes them, stores `{ key, sensitive, encrypt, expires }` under `session('bopomofo_captcha')`, and returns the PNG.
2. Browser displays the image; Laravel's session middleware has already set/refreshed the `laravel_session` cookie.
3. User types `ㄅㄆㄇㄈ` into the form, submits to `POST /register`.
4. Your controller calls `$request->validate(['captcha' => 'bopomofo_captcha'])`. The validator calls `BopomofoCaptcha::check($value)`, which reads the hash out of session, checks expiry, runs `Hash::check($input, $hash)`, and on success **forgets** the session entry. The captcha is now consumed.

**Stateless mode — same sequence, different state:**

1. Client (SPA / mobile) calls `GET /bopomofo-captcha/api/default`. The package picks 4 glyphs, hashes them, generates a random 16-char token (`bopomofo_captcha_aB3xZ9qLm2rt4w7v`), stores `{ hash, sensitive, encrypt }` in cache under that key with TTL = `expire` seconds, and returns `{ key, img }`.
2. Client shows the base64 image and **remembers the `key`** (hidden input or JS state).
3. User types the answer. Client POSTs `{ captcha, key }` to your endpoint.
4. Your controller calls `$request->validate(['captcha' => 'bopomofo_captcha_api:' . $req->key])`. The validator calls `BopomofoCaptcha::check_api($value, $key)`, which does `Cache::pull($key)` (read + delete in one op), bcrypt-checks, returns true/false. The cache entry is gone either way.

### Common pitfalls

**Session mode**

- *"My second captcha on the page always validates against the wrong one."* — Right, because the session only holds one answer. Switch to stateless and bind each captcha to its own field via `key`.
- *"The captcha works in dev but fails behind my reverse proxy."* — Check that the proxy forwards the `Cookie` header and that `SESSION_DOMAIN` covers the captcha URL.
- *"It works once, then fails forever."* — Successful checks are intentional: the session entry is wiped on success. The next form post needs a fresh image render first.

**Stateless mode**

- *"Every submission returns 422 invalid."* — Most likely the client forgot to include `key` in the POST body, or used the same `key` twice. `Cache::pull` deletes on read, so a retry after a failed validate needs a **new** `GET /bopomofo-captcha/api/...` call first.
- *"The key works locally but not in production."* — Make sure `CACHE_DRIVER` is shared across web workers (use `redis` / `database`, not `array` or per-worker `file` on multi-server deploys).
- *"`/bopomofo-captcha/api/...` returns CORS error."* — Add the path to `paths` in `config/cors.php` (or expand the `*` glob there).

---

### Flow 1 — Session mode (recommended for server-rendered apps)

```
┌─────────┐                ┌──────────────────────┐
│ Browser │                │ Laravel + this pkg   │
└────┬────┘                └──────────┬───────────┘
     │                                │
     │  GET /bopomofo-captcha           │
     │───────────────────────────────▶│  generate answer, hash → session
     │                                │
     │  ◀── 200 image/png ────────────│
     │                                │
     │  POST /your-form               │
     │      captcha=ㄅㄆㄇㄈ          │
     │───────────────────────────────▶│  validate with `bopomofo_captcha` rule
     │  ◀── 200 / 422 ────────────────│
```

The browser never sees the hashed answer — it lives in the session only.

---

### Flow 2 — Stateless / API mode (recommended for SPAs / mobile)

```
┌────────────┐              ┌──────────────────────┐
│ SPA client │              │ Laravel + this pkg   │
└─────┬──────┘              └──────────┬───────────┘
      │                                │
      │  GET /bopomofo-captcha/api       │
      │───────────────────────────────▶│  generate answer, hash → cache[key]
      │  ◀── 200 { key, img } ─────────│
      │                                │
      │  POST /your-endpoint           │
      │      { captcha, key }          │
      │───────────────────────────────▶│  validate with `bopomofo_captcha_api:key`
      │  ◀── 200 / 422 ────────────────│
```

The client passes `key` back on submit; the server retrieves the hashed answer from cache and verifies. Each `key` is **single-use** — a successful or failed check consumes it.

---

### `GET /bopomofo-captcha/{config?}`

Render and return the captcha PNG, storing the hashed answer in the session.

**Request:**

```http
GET /bopomofo-captcha HTTP/1.1
Cookie: laravel_session=...
```

**Response:**

```http
HTTP/1.1 200 OK
Content-Type: image/png
Cache-Control: no-cache, no-store, must-revalidate
```

The body is the raw PNG. Browser `<img>` tags can use this URL directly. To force a refresh, append a cache-busting query string (the helper `bopomofo_captcha_src()` already does this).

---

### `GET /bopomofo-captcha/api/{config?}`

Return the captcha as a base64-encoded data URI plus a single-use `key`.

**Request:**

```http
GET /bopomofo-captcha/api/default HTTP/1.1
Accept: application/json
```

**Response (200):**

```json
{
  "key": "bopomofo_captcha_aB3xZ9qLm2rt4w7v",
  "img": "data:image/png;base64,iVBORw0KGgoAAAANS..."
}
```

| Field | Type | Notes |
|-------|------|-------|
| `key` | string | Opaque token. Pass back when validating. Single-use. |
| `img` | string | `data:image/png;base64,<payload>` — drop into an `<img src>` directly. |

The `key` expires after `expire` seconds (default **60s**, configurable per profile).

---

### Submitting the answer

The host Laravel app exposes its own endpoint (e.g. `POST /register`) and validates the captcha using one of the two validation rules:

| Rule | Mode | Usage |
|------|------|-------|
| `bopomofo_captcha` | Session | `'captcha' => 'required|bopomofo_captcha'` |
| `bopomofo_captcha_api:{key}` | Stateless | `'captcha' => 'required|bopomofo_captcha_api:' . $req->input('key')` |

Profiles can be selected per-rule: `'captcha' => 'required|bopomofo_captcha:flat'`.

The `captcha` field value must be the **literal UTF-8 Bopomofo string** (`ㄅㄆㄇㄈ`), exactly as the user typed it via their Zhuyin IME. No tone marks, no spaces, no transliteration.

**Failure responses:**

When validation fails, Laravel returns the standard `422 Unprocessable Entity` (or redirects with `withErrors()` for non-AJAX requests):

```json
{
  "message": "The captcha is invalid.",
  "errors": {
    "captcha": ["The captcha is invalid."]
  }
}
```

---

### Worked example — Vanilla JS / fetch

```js
// 1. Fetch a captcha
const res = await fetch('/bopomofo-captcha/api/default');
const { key, img } = await res.json();

// 2. Display
document.querySelector('#captcha-img').src = img;
document.querySelector('input[name="key"]').value = key;

// 3. Submit (your own backend endpoint)
const submit = await fetch('/api/register', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
    body: JSON.stringify({
        email: 'user@example.com',
        captcha: document.querySelector('input[name="captcha"]').value,
        key: key,
    }),
});

if (submit.status === 422) {
    const { errors } = await submit.json();
    alert(errors.captcha?.[0] ?? 'invalid');
}
```

### Worked example — cURL

```bash
# Fetch
curl -s http://example.test/bopomofo-captcha/api/default | jq .

# Submit (host app endpoint)
curl -X POST http://example.test/api/register \
  -H 'Content-Type: application/json' \
  -d '{"captcha":"ㄅㄆㄇㄈ","key":"bopomofo_captcha_aB3xZ9qLm2rt4w7v"}'
```

---

### Notes & gotchas

- The answer is **stored only as a bcrypt hash** (optionally encrypted on top via `Crypt::encrypt`). It is never returned to the client.
- A captcha is **single-use**. A successful check wipes the stored hash; a second attempt with the same `key` or session entry will fail.
- The image URL changes every page render in session mode (the helper appends a random query string), so browsers never serve a stale captcha from cache.
- CORS: if the client is on a different origin, you must configure Laravel's `config/cors.php` to expose `/bopomofo-captcha/api/*`.
- The `length` of the answer (default **4**) is set per profile in `config/bopomofo-captcha.php`.

---

## 繁體中文

### 端點摘要

| Method | URL | 模式 | 狀態儲存 | 回傳 |
|--------|-----|------|----------|------|
| `GET` | `/bopomofo-captcha/{config?}` | Session | Laravel session | `image/png` |
| `GET` | `/bopomofo-captcha/api/{config?}` | 無狀態 | Laravel cache（以 key 索引） | `application/json` |

`{config}` 為 `config/bopomofo-captcha.php` 中定義的 profile 名稱，未指定時為 `default`。內建 profile：`default`、`flat`、`mini`、`inverse`。

---

### 我該選哪一種模式？

只要下面三題有任何一題答「是」，就用 **無狀態模式**：

1. **跨網域？** 前端跟 Laravel 後端不在同一個 domain（例如 SPA 部署在 Vercel/Netlify、API 在自己的 Laravel；或 Flutter / React Native app）。
2. **沒有 session cookie？** 你的認證走 token（Sanctum personal access token、Passport、JWT），客戶端根本不帶 `laravel_session` cookie。
3. **同一頁要放兩個以上驗證碼？** 例如同一頁有「註冊」跟「訂閱電子報」兩張表單。Session 模式整個 session 只記 **一份** 答案 —— 第二張圖片產生時會直接蓋掉第一張。

其他情況（純 Blade 表單、同網域、單一驗證碼）就用 **Session 模式**，最少程式、cookie 全自動。

### 並排比較

| 比較項 | Session 模式 | 無狀態 / API 模式 |
|--------|--------------|-------------------|
| 端點 | `GET /bopomofo-captcha/{config?}` | `GET /bopomofo-captcha/api/{config?}` |
| 回傳 | `image/png` 內容 | `{ "key": "...", "img": "data:..." }` JSON |
| 雜湊存哪裡 | Laravel session（看 app 的 `SESSION_DRIVER`） | Laravel cache，用回傳的 `key` 索引 |
| 客戶端要帶什麼 | session cookie —— 瀏覽器自動處理，僅限同網域 | `key` 字串 —— 自己存在 hidden input 或 JS state |
| 跨網域（CORS） | ✗ Cookie 通常被擋 | ✓ 設好 CORS 即可 |
| 行動端 / 原生 app | 麻煩（要管 cookie jar） | 直覺、自然 |
| 同頁多個驗證碼 | ✗ 只有最新的算數 | ✓ 每個 `key` 各佔一格 |
| 圖片傳輸大小 | 精簡（只傳 URL） | 多約 33%（base64 膨脹） |
| 一次性使用？ | 是 —— 驗證成功後清除 | 是 —— **不論成敗** 都會被清除 |
| 有效時間 | `expire` 秒（預設 60 秒） | `expire` 秒（預設 60 秒） |
| 送出時要 CSRF | 要（送到自家 app 的 `POST`） | 若端點受 session 保護則要；純 token API 不用 |

### 一次成功流程的詳細拆解

**Session 模式 —— 兩端各做了什麼：**

1. 瀏覽器請求 `/bopomofo-captcha`。套件隨機抽 4 個注音（例如 `ㄅㄆㄇㄈ`），bcrypt 雜湊後把 `{ key, sensitive, encrypt, expires }` 存進 `session('bopomofo_captcha')`，回傳 PNG。
2. 瀏覽器顯示圖片；Laravel 的 session middleware 已順便設好或刷新 `laravel_session` cookie。
3. 使用者輸入 `ㄅㄆㄇㄈ`，送出 `POST /register`。
4. 你的 controller 呼叫 `$request->validate(['captcha' => 'bopomofo_captcha'])`。Validator 內部呼叫 `BopomofoCaptcha::check($value)`，從 session 拿出 hash、檢查過期、跑 `Hash::check($input, $hash)`，成功後 **立刻 forget** session 紀錄。這張驗證碼已消耗。

**無狀態模式 —— 同樣的步驟、不同的儲存：**

1. 客戶端（SPA / 行動端）呼叫 `GET /bopomofo-captcha/api/default`。套件抽 4 個字、做 hash、產生 16 字元隨機 token（`bopomofo_captcha_aB3xZ9qLm2rt4w7v`），把 `{ hash, sensitive, encrypt }` 存進 cache（TTL = `expire` 秒），回傳 `{ key, img }`。
2. 客戶端顯示 base64 圖片，**把 `key` 記下來**（hidden input 或 JS state）。
3. 使用者輸入答案，客戶端 POST `{ captcha, key }` 到你的端點。
4. 你的 controller 呼叫 `$request->validate(['captcha' => 'bopomofo_captcha_api:' . $req->key])`。Validator 內部呼叫 `BopomofoCaptcha::check_api($value, $key)`，做 `Cache::pull($key)`（讀 + 刪 一次完成），bcrypt 比對，回傳 true/false。**不論結果**，cache entry 都已消失。

### 常見地雷

**Session 模式**

- 「同一頁兩個驗證碼，第二個老是驗到第一個的答案」 —— 沒錯，因為 session 只存一份。換成無狀態模式，每個驗證碼各自綁自己的 `key`。
- 「dev 沒問題，過了反向代理就壞掉」 —— 確認 proxy 有轉發 `Cookie` header，`SESSION_DOMAIN` 設定也涵蓋到驗證碼的網址。
- 「第一次驗證成功，之後就一直失敗」 —— 這是設計上正確的：成功後 session 紀錄會被清掉。下一次送表單前，前端要重新打一次 `<img>` 拿新的圖。

**無狀態模式**

- 「每次都回 422 invalid」 —— 八成是客戶端沒把 `key` 一起 POST 上來，或是同一個 `key` 用了兩次。`Cache::pull` 一讀就刪，驗證失敗後想重試一定要先打 `GET /bopomofo-captcha/api/...` 重拿新的。
- 「本機可以、線上不行」 —— 確認 `CACHE_DRIVER` 在多 worker / 多機器架構下是共享的（用 `redis` / `database`，不要用 `array` 或單機 `file`）。
- 「`/bopomofo-captcha/api/...` 噴 CORS 錯誤」 —— 把這條 path 加進 `config/cors.php` 的 `paths`（或擴大那邊的 `*` 樣式）。

---

### 流程一 —— Session 模式（伺服器渲染的網頁推薦）

```
┌─────────┐                ┌──────────────────────┐
│ 瀏覽器  │                │ Laravel + 本套件     │
└────┬────┘                └──────────┬───────────┘
     │                                │
     │  GET /bopomofo-captcha           │
     │───────────────────────────────▶│  產生答案 → bcrypt 存進 session
     │  ◀── 200 image/png ────────────│
     │                                │
     │  POST /你的表單                │
     │      captcha=ㄅㄆㄇㄈ          │
     │───────────────────────────────▶│  以 `bopomofo_captcha` 規則驗證
     │  ◀── 200 / 422 ────────────────│
```

雜湊後的答案只存在於 session，不會回傳給瀏覽器。

---

### 流程二 —— 無狀態 / API 模式（SPA、行動端推薦）

```
┌────────────┐              ┌──────────────────────┐
│ SPA 客戶端 │              │ Laravel + 本套件     │
└─────┬──────┘              └──────────┬───────────┘
      │                                │
      │  GET /bopomofo-captcha/api       │
      │───────────────────────────────▶│  產生答案 → bcrypt 存進 cache[key]
      │  ◀── 200 { key, img } ─────────│
      │                                │
      │  POST /你的端點                │
      │      { captcha, key }          │
      │───────────────────────────────▶│  以 `bopomofo_captcha_api:key` 驗證
      │  ◀── 200 / 422 ────────────────│
```

客戶端在送出時把 `key` 帶回；伺服端用 `key` 從 cache 拿出 hash 比對。每個 `key` 為 **一次性**，驗證後（不論成敗）即失效。

---

### `GET /bopomofo-captcha/{config?}`

產生並回傳 PNG，雜湊答案存進 session。

**Request：**

```http
GET /bopomofo-captcha HTTP/1.1
Cookie: laravel_session=...
```

**Response：**

```http
HTTP/1.1 200 OK
Content-Type: image/png
Cache-Control: no-cache, no-store, must-revalidate
```

Body 為原始 PNG。瀏覽器的 `<img>` 標籤可直接指向此 URL。若要強制重新整理圖片，在 URL 後加上 cache-busting query string（`bopomofo_captcha_src()` 已自動處理）。

---

### `GET /bopomofo-captcha/api/{config?}`

以 base64 data URI 回傳圖片，並附帶一次性 `key`。

**Request：**

```http
GET /bopomofo-captcha/api/default HTTP/1.1
Accept: application/json
```

**Response（200）：**

```json
{
  "key": "bopomofo_captcha_aB3xZ9qLm2rt4w7v",
  "img": "data:image/png;base64,iVBORw0KGgoAAAANS..."
}
```

| 欄位 | 型別 | 說明 |
|------|------|------|
| `key` | string | 不透明 token，驗證時帶回。一次性使用。 |
| `img` | string | `data:image/png;base64,<payload>`，可直接放進 `<img src>`。 |

`key` 在 `expire` 秒後過期（預設 **60 秒**，可在 profile 中設定）。

---

### 答案提交

宿主 Laravel app 應自行提供端點（如 `POST /register`），並使用以下任一驗證規則：

| 規則 | 模式 | 用法 |
|------|------|------|
| `bopomofo_captcha` | Session | `'captcha' => 'required|bopomofo_captcha'` |
| `bopomofo_captcha_api:{key}` | 無狀態 | `'captcha' => 'required|bopomofo_captcha_api:' . $req->input('key')` |

可指定 profile：`'captcha' => 'required|bopomofo_captcha:flat'`。

`captcha` 欄位的值必須是 **UTF-8 注音符號字串**（如 `ㄅㄆㄇㄈ`），即使用者透過注音輸入法實際輸入的內容。不含聲調、不含空白、不可轉拼音。

**驗證失敗時：**

Laravel 回傳標準 `422 Unprocessable Entity`（非 AJAX 請求則以 `withErrors()` 重導）：

```json
{
  "message": "The captcha is invalid.",
  "errors": {
    "captcha": ["The captcha is invalid."]
  }
}
```

---

### 範例 —— Vanilla JS / fetch

```js
// 1. 取得驗證碼
const res = await fetch('/bopomofo-captcha/api/default');
const { key, img } = await res.json();

// 2. 顯示
document.querySelector('#captcha-img').src = img;
document.querySelector('input[name="key"]').value = key;

// 3. 送出（向你自己的後端端點）
const submit = await fetch('/api/register', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf },
    body: JSON.stringify({
        email: 'user@example.com',
        captcha: document.querySelector('input[name="captcha"]').value,
        key: key,
    }),
});

if (submit.status === 422) {
    const { errors } = await submit.json();
    alert(errors.captcha?.[0] ?? 'invalid');
}
```

### 範例 —— cURL

```bash
# 取得
curl -s http://example.test/bopomofo-captcha/api/default | jq .

# 送出（你自己的端點）
curl -X POST http://example.test/api/register \
  -H 'Content-Type: application/json' \
  -d '{"captcha":"ㄅㄆㄇㄈ","key":"bopomofo_captcha_aB3xZ9qLm2rt4w7v"}'
```

---

### 注意事項

- 答案只以 **bcrypt hash** 形式儲存（可額外用 `Crypt::encrypt` 加密一層），永不回傳給客戶端。
- 驗證碼為 **一次性使用**。驗證成功後立刻清除；同一個 `key` 或 session 紀錄再次送出必定失敗。
- Session 模式下，圖片網址每次渲染都會變動（helper 會自動加上隨機 query string），瀏覽器不會從快取拿到舊驗證碼。
- CORS：若客戶端位於不同網域，需在 Laravel 的 `config/cors.php` 中開放 `/bopomofo-captcha/api/*`。
- 答案長度（預設 **4**）在 `config/bopomofo-captcha.php` 的各 profile 中設定。
