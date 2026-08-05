<?php
/**
 * 注文書画像から明細（品名・数量・単価）を抽出して JSON で返す。
 * OpenAI Vision (gpt-4o-mini) を使用。APIキーは環境変数 OPENAI_API_KEY から取得する。
 */
require_once __DIR__ . '/../../config/env.php';

header('Content-Type: application/json; charset=UTF-8');

function jsonError(string $message, int $status = 400): void {
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('POSTメソッドでリクエストしてください', 405);
}

$apiKey = getOpenAiApiKey();
if (!$apiKey) {
    jsonError('OPENAI_API_KEY が設定されていません。config/.env または環境変数に設定してください。', 500);
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    jsonError('画像のアップロードに失敗しました');
}

$maxBytes = 10 * 1024 * 1024;
if ($_FILES['image']['size'] > $maxBytes) {
    jsonError('画像サイズが大きすぎます（上限10MB）');
}

$mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($_FILES['image']['tmp_name']);
$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($mimeType, $allowedMimeTypes, true)) {
    jsonError('対応していない画像形式です（JPEG/PNG/GIF/WebP）');
}

$dataUrl = 'data:' . $mimeType . ';base64,' . base64_encode(file_get_contents($_FILES['image']['tmp_name']));

$payload = [
    'model' => 'gpt-4o-mini',
    'temperature' => 0,
    'response_format' => ['type' => 'json_object'],
    'messages' => [
        [
            'role' => 'system',
            'content' => 'あなたは注文書の読み取りアシスタントです。画像から明細行を抽出し、JSONのみを返してください。'
                . '形式: {"items":[{"item_name":"品名","quantity":1,"unit":"式","unit_price":1000}]}。'
                . 'quantity と unit_price は数値（円、税抜、カンマや通貨記号を除く）。'
                . '読み取れない項目は quantity=1, unit_price=0, unit="式" とし、明細が無い場合は items を空配列にしてください。'
        ],
        [
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => 'この注文書画像から明細（品名・数量・単位・単価）を抽出してください。'],
                ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]]
            ]
        ]
    ]
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
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    jsonError('OpenAI APIへの接続に失敗しました: ' . $curlError, 502);
}
if ($httpCode !== 200) {
    $detail = json_decode($response, true)['error']['message'] ?? '';
    jsonError('OpenAI APIエラー (HTTP ' . $httpCode . ') ' . $detail, 502);
}

$content = json_decode($response, true)['choices'][0]['message']['content'] ?? '';
$parsed = json_decode($content, true);
if (!is_array($parsed) || !isset($parsed['items']) || !is_array($parsed['items'])) {
    jsonError('抽出結果を解析できませんでした', 502);
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
        'quantity' => max(1, (int)round((float)($item['quantity'] ?? 1))),
        'unit' => trim((string)($item['unit'] ?? '')) ?: '式',
        'unit_price' => max(0, (int)round((float)($item['unit_price'] ?? 0)))
    ];
}

echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
