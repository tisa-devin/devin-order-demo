<?php
/**
 * 外部APIキーの読み込み。
 *
 * 実キーは以下のいずれかで与える（どちらもリポジトリには含めない）:
 *   1. config/api_keys.local.php  … api_keys.local.php.example をコピーして記入
 *   2. 環境変数 OPENAI_API_KEY
 */

define('API_KEYS_LOCAL_FILE', __DIR__ . '/api_keys.local.php');

function getApiKeys(): array {
    static $keys = null;
    if ($keys === null) {
        $keys = [];
        if (is_file(API_KEYS_LOCAL_FILE)) {
            // BOM や余分な空行がレスポンスに混入しないよう読み込み中の出力を捨てる
            ob_start();
            $keys = (array)require API_KEYS_LOCAL_FILE;
            ob_end_clean();
        }
    }
    return $keys;
}

function getOpenAiApiKey(): ?string {
    $key = getApiKeys()['openai_api_key'] ?? getenv('OPENAI_API_KEY') ?: '';
    $key = trim((string)$key);
    return $key === '' ? null : $key;
}

function getOpenAiVisionModel(): string {
    $model = getApiKeys()['openai_vision_model'] ?? getenv('OPENAI_VISION_MODEL') ?: '';
    $model = trim((string)$model);
    return $model === '' ? 'gpt-4o-mini' : $model;
}
