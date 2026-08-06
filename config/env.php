<?php
/**
 * 環境変数の読み込み。
 * 実行環境の環境変数を優先し、無ければリポジトリ直下の .env を読む。
 * .env は .gitignore 済みで、APIキーなどをコードに直書きしないための仕組み。
 */

function loadEnvFile(string $path): void
{
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
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[0] === substr($value, -1)) {
            $value = substr($value, 1, -1);
        }
        if ($name !== '' && getenv($name) === false) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
        }
    }
}

/**
 * 環境変数を取得する。存在しなければ $default を返す。
 */
function env(string $name, ?string $default = null): ?string
{
    loadEnvFile(__DIR__ . '/../.env');

    $value = getenv($name);
    if ($value === false || $value === '') {
        $value = $_ENV[$name] ?? null;
    }
    return ($value === null || $value === '') ? $default : $value;
}
