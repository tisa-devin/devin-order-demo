---
name: testing-order-demo
description: How to run and E2E-test the devin-order-demo PHP/SQLite app (Japanese UI) on a Windows VM, including the BASE_PATH gotcha, PHP-warning visibility, and per-screen UI entry points.
---

# Testing devin-order-demo (PHP 8.4 + SQLite, no framework)

## Serving the app

The VM used for this repo has been Windows + PowerShell (not Linux/bash). Assume PowerShell syntax:
`&&` / `||` / `2>/dev/null` do NOT work, and `cmd /c "... > file"` is needed for byte-exact redirection
(PowerShell's `>` writes UTF-16 and will break PHP files).

Recommended run method (works regardless of whether PHP is installed on the host):

```powershell
docker run -d --name order-demo -p 3000:3000 `
  -v C:\path\to\repo:/var/www/html/devin-order-demo-main `
  -w /var/www/html php:8.4-cli php -S 0.0.0.0:3000 -t /var/www/html
docker exec order-demo php /var/www/html/devin-order-demo-main/config/init_db.php
docker exec order-demo php /var/www/html/devin-order-demo-main/data/seed.php
```

**Critical**: `config/database.php` hardcodes `define('BASE_PATH', '/devin-order-demo-main')`, so the app
MUST be served under that path prefix, otherwise all nav links/redirect-relative assets break.
Entry URL: `http://localhost:3000/devin-order-demo-main/`.

- DB file: `data/database.sqlite`. Re-seed anytime with the two commands above.
- No auth/login of any kind. No secrets required.

## PHP warnings are visible in the browser

`display_errors=1` in the `php:8.4-cli` image, so `Warning:` / `Fatal error:` text renders at the top of the
page. This makes "no PHP warning on screen" a directly observable assertion — take a screenshot of the page
top after every POST.

## UI entry points (Japanese UI)

Nav: ダッシュボード / マスタ / 見積管理 / 受注管理 / 発注管理 / 売上管理.

- 見積: `pages/estimates/list.php` → 「新規見積」 or 行の「編集」. Save button = 「保存」, delete = 「削除」 (JS confirm).
- 受注: `pages/orders/list.php` → 「新規受注」. Conversion from an estimate: 見積一覧 row link 「受注変換」 →
  `pages/orders/edit.php?from_estimate=<estimate_id>` (prefills customer/subject/details).
- 発注: **do not** use 「新規発注」 with no query param — the 受注 select is rendered `disabled`, so `order_id`
  is never posted and the server rejects with 「仕入先、受注、発注日は必須です」 (pre-existing dead end, present on
  main too). Use 受注一覧 row link 「発注」 → `pages/purchases/edit.php?order_id=<order_id>`, then click the
  「受注明細から選択」 chip to populate a detail line.
- 売上: use 受注一覧 row link 「売上」 → `pages/sales/edit.php?order_id=<order_id>` (auto-fills amounts).
- CSV regression (`header('Content-Type: text/csv')`): 売上一覧 → tick a row checkbox → 「CSV出力」 (confirm).
  File is Shift_JIS; decode with `[System.Text.Encoding]::GetEncoding(932)` to read it.

## Triggering server-side validation errors

Most required fields also carry HTML5 `required`, so the browser blocks submission and the PHP validation
branch is never reached. Two ways to reach it:
1. `pages/purchases/edit.php` with no `order_id` — reaches the server-side error branch with no code changes.
2. Temporarily remove the `required` attribute from the field under test, then restore with
   `git checkout -- <file>`.

If you must temporarily edit a PHP file, do it byte-safely, e.g.:

```powershell
$enc = New-Object System.Text.UTF8Encoding($false)
$t = [System.IO.File]::ReadAllText($p, $enc)
[System.IO.File]::WriteAllText($p, $t.Replace('old','new'), $enc)
```

Plain `Set-Content -Encoding UTF8` adds a **BOM**, which itself emits output before `header()` and would
fake the very "headers already sent" bug you may be testing. Always `git diff` after a temp edit and
`git checkout --` to restore.

## Reproducing a "before" state for regression proof

`cmd /c "git show origin/main:pages/x/edit.php > file.bak"` then `Copy-Item file.bak <target>` (byte-safe),
demonstrate the old bug in the UI, then `git checkout -- <target>`.

## Devin Secrets Needed

None — the app has no authentication and no external services.
