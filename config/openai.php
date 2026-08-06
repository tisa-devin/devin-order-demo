<?php
require_once __DIR__ . '/env.php';

/**
 * OpenAI Vision で注文書画像から明細（品名・数量・単価）を抽出する。
 *
 * @return array{items: array<int, array{item_name: string, quantity: int, unit: string, unit_price: int}>}
 * @throws RuntimeException
 */
function extractOrderItemsFromImage(string $imageData, string $mimeType): array {
    $apiKey = getOpenAiApiKey();
    if (!$apiKey) {
        throw new RuntimeException('OPENAI_API_KEY が設定されていません。config/.env.example を config/.env にコピーしてキーを設定してください。');
    }

    $prompt = <<<PROMPT
    あなたは注文書（発注書）の読み取りアシスタントです。画像から明細行を抽出してください。
    次のJSON形式のみを返してください。説明文は不要です。
    {"items":[{"item_name":"品名","quantity":1,"unit":"式","unit_price":1000}]}
    - quantity と unit_price は数値（カンマや通貨記号を除いた整数）
    - unit が読み取れない場合は "式"
    - 明細が読み取れない場合は {"items":[]}
    PROMPT;

    $payload = [
        'model' => getOpenAiModel(),
        'response_format' => ['type' => 'json_object'],
        'messages' => [[
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => $prompt],
                ['type' => 'image_url', 'image_url' => [
                    'url' => 'data:' . $mimeType . ';base64,' . base64_encode($imageData)
                ]]
            ]
        ]]
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey
        ],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE)
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('OpenAI APIへの接続に失敗しました: ' . $curlError);
    }

    $body = json_decode($response, true);
    if ($status !== 200) {
        throw new RuntimeException('OpenAI APIエラー (HTTP ' . $status . '): ' . ($body['error']['message'] ?? '詳細不明'));
    }

    $content = $body['choices'][0]['message']['content'] ?? '';
    $parsed = json_decode($content, true);
    if (!is_array($parsed) || !isset($parsed['items']) || !is_array($parsed['items'])) {
        throw new RuntimeException('抽出結果を解釈できませんでした。');
    }

    $items = [];
    foreach ($parsed['items'] as $item) {
        $name = trim((string)($item['item_name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $items[] = [
            'item_name' => $name,
            'quantity' => max(1, (int)($item['quantity'] ?? 1)),
            'unit' => trim((string)($item['unit'] ?? '')) ?: '式',
            'unit_price' => max(0, (int)($item['unit_price'] ?? 0))
        ];
    }

    return ['items' => $items];
}
