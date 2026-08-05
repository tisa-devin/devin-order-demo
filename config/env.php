<?php
/**
 * 環境変数の読み込み。
 * config/.env（gitignore 済み）があれば読み込み、既存の環境変数を優先する。
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

function env(string $name, ?string $default = null): ?string {
    loadEnvFile(__DIR__ . '/.env');
    $value = getenv($name);
    if ($value === false || $value === '') {
        $value = $_ENV[$name] ?? null;
    }
    return ($value === false || $value === null || $value === '') ? $default : $value;
}

function getOpenAiApiKey(): ?string {
    return env('OPENAI_API_KEY');
}
