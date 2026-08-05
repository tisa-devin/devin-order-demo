<?php
/**
 * アプリケーション設定。
 * APIキーは config/secrets.php（Git管理対象外）または環境変数から読み込む。
 */

function loadSecrets(): array {
    static $secrets = null;
    if ($secrets === null) {
        $path = __DIR__ . '/secrets.php';
        $secrets = [];
        if (is_file($path)) {
            $loaded = require $path;
            if (is_array($loaded)) {
                $secrets = $loaded;
            }
        }
    }
    return $secrets;
}

function getOpenAiApiKey(): string {
    $secrets = loadSecrets();
    $key = trim((string)($secrets['OPENAI_API_KEY'] ?? ''));
    if ($key === '') {
        $key = trim((string)(getenv('OPENAI_API_KEY') ?: ''));
    }
    return $key;
}
