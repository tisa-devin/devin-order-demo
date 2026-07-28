<?php
/**
 * 環境変数の読み込み。
 * 優先順位: 実際の環境変数 > config/.env（Git管理外）
 * APIキーなどの秘匿値はコードに直書きせず、必ずここ経由で取得する。
 */
function loadEnvFile(string $path): void {
    static $loaded = [];
    if (isset($loaded[$path]) || !is_readable($path)) {
        return;
    }
    $loaded[$path] = true;

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[0] === substr($value, -1)) {
            $value = substr($value, 1, -1);
        }
        if ($key !== '' && getenv($key) === false) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

function env(string $key, ?string $default = null): ?string {
    loadEnvFile(__DIR__ . '/.env');

    $value = getenv($key);
    if ($value === false || $value === '') {
        $value = $_ENV[$key] ?? '';
    }
    return $value === '' ? $default : $value;
}
