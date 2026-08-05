<?php
/**
 * 外部APIキーの読み込み
 *
 * 優先順位:
 *   1. 環境変数（OPENAI_API_KEY など）
 *   2. config/api_keys.local.php が返す連想配列（.gitignore 済み。実キーはこちらに置く）
 *
 * config/api_keys.local.php の例は api_keys.local.php.example を参照。
 */

function getApiKey(string $name): string {
    static $localKeys = null;

    $fromEnv = getenv($name);
    if (is_string($fromEnv) && $fromEnv !== '') {
        return $fromEnv;
    }

    if ($localKeys === null) {
        $localFile = __DIR__ . '/api_keys.local.php';
        $localKeys = file_exists($localFile) ? (require $localFile) : [];
        if (!is_array($localKeys)) {
            $localKeys = [];
        }
    }

    return trim((string)($localKeys[$name] ?? ''));
}
