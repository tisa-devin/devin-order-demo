<?php
/**
 * OpenAI Vision（gpt-4o-mini）で注文書画像から明細を抽出する処理。
 * APIキーは環境変数 OPENAI_API_KEY から読み込む（コードへの直書きは禁止）。
 */
require_once __DIR__ . '/env.php';

const OPENAI_VISION_MODEL = 'gpt-4o-mini';
const OPENAI_ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
const OPENAI_MAX_IMAGE_BYTES = 10 * 1024 * 1024;

class OpenAiException extends RuntimeException {}

/**
 * 注文書画像（ローカルの一時ファイル）から明細行を抽出する。
 *
 * @return array<int, array{item_name: string, quantity: int, unit: string, unit_price: int}>
 */
function extractOrderItemsFromImage(string $imagePath, string $mimeType): array
{
    $apiKey = env('OPENAI_API_KEY');
    if ($apiKey === null) {
        throw new OpenAiException('OPENAI_API_KEY が設定されていません。.env または環境変数に設定してください。');
    }
    if (!in_array($mimeType, OPENAI_ALLOWED_IMAGE_TYPES, true)) {
        throw new OpenAiException('対応していない画像形式です: ' . $mimeType);
    }

    $binary = file_get_contents($imagePath);
    if ($binary === false) {
        throw new OpenAiException('画像の読み込みに失敗しました。');
    }
    if (strlen($binary) > OPENAI_MAX_IMAGE_BYTES) {
        throw new OpenAiException('画像サイズが大きすぎます（上限10MB）。');
    }

    $payload = [
        'model' => OPENAI_VISION_MODEL,
        'response_format' => ['type' => 'json_object'],
        'messages' => [
            [
                'role' => 'system',
                'content' => '注文書の画像から明細行を抽出するアシスタントです。'
                    . 'JSONのみを返してください。形式: {"items":[{"item_name":"品名","quantity":1,"unit":"式","unit_price":1000}]}。'
                    . 'quantity と unit_price は数値（円、税抜、カンマや通貨記号なし）。読み取れない項目は quantity=1, unit="式", unit_price=0 とし、'
                    . '明細が見つからない場合は {"items":[]} を返してください。',
            ],
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => 'この注文書画像の明細（品名・数量・単位・単価）を抽出してください。'],
                    [
                        'type' => 'image_url',
                        'image_url' => ['url' => 'data:' . $mimeType . ';base64,' . base64_encode($binary)],
                    ],
                ],
            ],
        ],
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new OpenAiException('OpenAI APIへの接続に失敗しました: ' . $curlError);
    }
    $decoded = json_decode($response, true);
    if ($status !== 200) {
        $apiMessage = $decoded['error']['message'] ?? '不明なエラー';
        throw new OpenAiException("OpenAI APIがエラーを返しました (HTTP $status): $apiMessage");
    }

    $content = $decoded['choices'][0]['message']['content'] ?? '';
    $parsed = json_decode($content, true);
    if (!is_array($parsed) || !isset($parsed['items']) || !is_array($parsed['items'])) {
        throw new OpenAiException('抽出結果を解釈できませんでした。');
    }

    $items = [];
    foreach ($parsed['items'] as $item) {
        if (!is_array($item)) {
            continue;
        }
        $name = trim((string)($item['item_name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $items[] = [
            'item_name' => $name,
            'quantity' => max(1, (int)($item['quantity'] ?? 1)),
            'unit' => trim((string)($item['unit'] ?? '')) ?: '式',
            'unit_price' => max(0, (int)round((float)($item['unit_price'] ?? 0))),
        ];
    }

    return $items;
}
