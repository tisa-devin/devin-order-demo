<?php
/**
 * OpenAI API 設定の読み込み。
 * 実キーはリポジトリにコミットしない（config/api_keys.local.php は .gitignore 済み）。
 *
 * キーの探索優先順:
 *   1. 環境変数 OPENAI_API_KEY
 *   2. config/api_keys.local.php が返す連想配列の 'OPENAI_API_KEY'
 */

if (!defined('OPENAI_VISION_MODEL')) {
    define('OPENAI_VISION_MODEL', 'gpt-4o-mini');
}

function getOpenAiApiKey(): string {
    $env = getenv('OPENAI_API_KEY');
    if ($env !== false && trim($env) !== '') {
        return trim($env);
    }

    $localFile = __DIR__ . '/api_keys.local.php';
    if (is_file($localFile)) {
        $keys = require $localFile;
        if (is_array($keys) && !empty($keys['OPENAI_API_KEY'])) {
            return trim((string)$keys['OPENAI_API_KEY']);
        }
    }

    return '';
}
