<?php
/**
 * 環境変数の読み込み。
 * 実際のAPIキーはコードに直書きせず、環境変数または config/.env（.gitignore対象）から読み込む。
 */

function loadEnvFile(string $path = __DIR__ . '/.env'): void {
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
        $value = trim(trim($value), "\"'");
        if ($key !== '' && getenv($key) === false) {
            putenv("{$key}={$value}");
            $_ENV[$key] = $value;
        }
    }
}

function env(string $key, ?string $default = null): ?string {
    loadEnvFile();
    $value = getenv($key);
    if ($value === false || $value === '') {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
    }
    return ($value === null || $value === '') ? $default : $value;
}

function getOpenAiApiKey(): ?string {
    return env('OPENAI_API_KEY');
}

function getOpenAiModel(): string {
    return env('OPENAI_MODEL', 'gpt-4o-mini');
}
