<?php
/**
 * 外部APIキーの読み込み。
 * 優先順は「環境変数 > config/api_keys.php」。実キーはリポジトリに含めない。
 */

function getApiKey(string $name): string {
    static $keys = null;

    $envName = strtoupper($name);
    $fromEnv = getenv($envName);
    if ($fromEnv !== false && trim($fromEnv) !== '') {
        return trim($fromEnv);
    }

    if ($keys === null) {
        $path = __DIR__ . '/api_keys.php';
        $keys = file_exists($path) ? (array)require $path : [];
    }

    return trim((string)($keys[strtolower($name)] ?? ''));
}
