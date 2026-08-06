<?php
// 外部APIキーの読み込み。環境変数を優先し、無ければ api_keys.local.php（Git管理外）から取得する。

function getApiKey(string $name): ?string {
    static $localKeys = null;

    $env = getenv($name);
    if ($env !== false && $env !== '') {
        return $env;
    }

    if ($localKeys === null) {
        $file = __DIR__ . '/api_keys.local.php';
        $localKeys = is_file($file) ? require $file : [];
    }

    $key = $localKeys[$name] ?? '';
    return $key !== '' ? $key : null;
}
