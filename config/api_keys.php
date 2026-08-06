<?php
/**
 * 外部APIキーの読み込み。
 *
 * 優先順位:
 *   1. 環境変数（例: OPENAI_API_KEY）
 *   2. config/api_keys.local.php が返す連想配列（.gitignore 済み。api_keys.local.php.example を参照）
 */
function getApiKey(string $name): ?string {
    static $localKeys = null;

    $env = getenv($name);
    if (is_string($env) && $env !== '') {
        return $env;
    }

    if ($localKeys === null) {
        $localPath = __DIR__ . '/api_keys.local.php';
        $loaded = file_exists($localPath) ? require $localPath : [];
        $localKeys = is_array($loaded) ? $loaded : [];
    }

    $value = $localKeys[$name] ?? null;
    return (is_string($value) && $value !== '') ? $value : null;
}
