<?php
/**
 * .env（リポジトリ管理外）と環境変数から設定値を読み込む。
 * 環境変数が優先され、.env は開発環境向けのフォールバック。
 */

function loadEnvFile(string $path = __DIR__ . '/../.env'): void {
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
        if ($key !== '' && env($key) === null) {
            $_ENV[$key] = $value;
        }
    }
}

function env(string $key): ?string {
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);
    return ($value === false || $value === null || $value === '') ? null : (string)$value;
}

function getOpenAiApiKey(): ?string {
    loadEnvFile();
    return env('OPENAI_API_KEY');
}

function getOpenAiModel(): string {
    loadEnvFile();
    return env('OPENAI_VISION_MODEL') ?? 'gpt-4o-mini';
}
