<?php
// OpenAI API の設定値を取得する。
// 優先順位: 環境変数 > config/config.php の定数 > 既定値

if (file_exists(__DIR__ . '/config.php')) {
    require_once __DIR__ . '/config.php';
}

function getOpenAIApiKey(): string {
    $key = getenv('OPENAI_API_KEY');
    if (is_string($key) && trim($key) !== '') {
        return trim($key);
    }
    if (defined('OPENAI_API_KEY_CONFIG')) {
        return trim((string)OPENAI_API_KEY_CONFIG);
    }
    return '';
}

function getOpenAIModel(): string {
    $model = getenv('OPENAI_MODEL');
    if (is_string($model) && trim($model) !== '') {
        return trim($model);
    }
    if (defined('OPENAI_MODEL_CONFIG') && trim((string)OPENAI_MODEL_CONFIG) !== '') {
        return trim((string)OPENAI_MODEL_CONFIG);
    }
    return 'gpt-4o';
}
