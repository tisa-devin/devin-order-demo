<?php
/**
 * 注文書画像から明細（品名・数量・単価）を抽出する AJAX エンドポイント。
 * 抽出結果を返すだけで、DBへの保存は行わない。
 */
require_once __DIR__ . '/../../config/env.php';

header('Content-Type: application/json; charset=UTF-8');

function respondError(string $message, int $status = 400): never {
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondError('POSTでリクエストしてください', 405);
}

$apiKey = env('OPENAI_API_KEY');
if (!$apiKey) {
    respondError('OPENAI_API_KEY が設定されていません。config/.env または環境変数に設定してください。', 500);
}

$file = $_FILES['image'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    respondError('画像がアップロードされていません');
}
if ($file['size'] > 10 * 1024 * 1024) {
    respondError('画像サイズが大きすぎます（上限10MB）');
}

$mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
$allowedTypes = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
if (!in_array($mimeType, $allowedTypes, true)) {
    respondError('対応していない画像形式です（PNG/JPEG/GIF/WebP）');
}

$dataUrl = 'data:' . $mimeType . ';base64,' . base64_encode(file_get_contents($file['tmp_name']));

$schema = [
    'type' => 'object',
    'properties' => [
        'items' => [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'item_name' => ['type' => 'string'],
                    'quantity' => ['type' => 'integer'],
                    'unit' => ['type' => 'string'],
                    'unit_price' => ['type' => 'integer'],
                ],
                'required' => ['item_name', 'quantity', 'unit', 'unit_price'],
                'additionalProperties' => false,
            ],
        ],
    ],
    'required' => ['items'],
    'additionalProperties' => false,
];

$payload = [
    'model' => 'gpt-4o-mini',
    'temperature' => 0,
    'messages' => [
        [
            'role' => 'system',
            'content' => '注文書画像から明細行を抽出するアシスタント。品名・数量・単位・単価を読み取り、金額や合計行・小計行・消費税行は明細に含めない。単価は税抜の整数（円）。数量が読み取れない場合は1、単位が無い場合は「式」とする。',
        ],
        [
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => 'この注文書の明細を抽出してください。'],
                ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
            ],
        ],
    ],
    'response_format' => [
        'type' => 'json_schema',
        'json_schema' => ['name' => 'order_items', 'strict' => true, 'schema' => $schema],
    ],
];

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 120,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    respondError('OpenAI APIへの接続に失敗しました: ' . $curlError, 502);
}

$body = json_decode($response, true);
if ($httpCode !== 200) {
    respondError('OpenAI APIエラー: ' . ($body['error']['message'] ?? ('HTTP ' . $httpCode)), 502);
}

$content = $body['choices'][0]['message']['content'] ?? '';
$parsed = json_decode($content, true);
if (!is_array($parsed) || !isset($parsed['items']) || !is_array($parsed['items'])) {
    respondError('抽出結果を解釈できませんでした', 502);
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
        'unit_price' => max(0, (int)($item['unit_price'] ?? 0)),
    ];
}

echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
