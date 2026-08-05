<?php
/**
 * 注文書画像をOpenAI Visionで解析し、受注明細の候補をJSONで返すエンドポイント。
 * DBは更新せず、保存は画面上でユーザーが確認してから行う。
 */
require_once __DIR__ . '/../../config/env.php';

header('Content-Type: application/json; charset=UTF-8');

const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;
const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

function respondError(string $message, int $status = 400): never {
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondError('POSTのみ対応しています', 405);
}

$apiKey = getOpenAiApiKey();
if ($apiKey === null) {
    respondError('OPENAI_API_KEY が設定されていません。config/.env（.env.example 参照）または環境変数を設定してください。', 500);
}

$file = $_FILES['image'] ?? null;
if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    respondError('画像ファイルをアップロードしてください');
}
if ($file['size'] > MAX_UPLOAD_BYTES) {
    respondError('画像サイズが大きすぎます（上限10MB）');
}

$mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
if (!in_array($mimeType, ALLOWED_MIME_TYPES, true)) {
    respondError('対応していない画像形式です（JPEG/PNG/WebP/GIF）');
}

$dataUrl = 'data:' . $mimeType . ';base64,' . base64_encode(file_get_contents($file['tmp_name']));

$prompt = <<<PROMPT
あなたは注文書（発注書）を読み取る担当者です。画像から明細行を抽出してください。

制約:
- item_name は品名・品目名（文字列）
- quantity は数量（整数）
- unit は単位（式・個・本など）
- unit_price は税抜の単価（整数、円。カンマや通貨記号は除去する。金額と数量から計算できる場合は 金額 ÷ 数量 を単価とする）
- 読み取れない項目は推測せず null にする（既定値で埋めない）
- 小計・消費税・合計などの集計行は明細に含めない
- 読み取れない場合は items を空配列にする
PROMPT;

$payload = [
    'model' => getOpenAiModel(),
    'messages' => [[
        'role' => 'user',
        'content' => [
            ['type' => 'text', 'text' => $prompt],
            ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
        ],
    ]],
    'response_format' => [
        'type' => 'json_schema',
        'json_schema' => [
            'name' => 'order_items',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['items'],
                'properties' => [
                    'items' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'additionalProperties' => false,
                            'required' => ['item_name', 'quantity', 'unit', 'unit_price'],
                            'properties' => [
                                'item_name' => ['type' => ['string', 'null']],
                                'quantity' => ['type' => ['integer', 'null']],
                                'unit' => ['type' => ['string', 'null']],
                                'unit_price' => ['type' => ['integer', 'null']],
                            ],
                        ],
                    ],
                ],
            ],
        ],
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

$content = $body['choices'][0]['message']['content'] ?? null;
$parsed = $content === null ? null : json_decode($content, true);
if (!is_array($parsed) || !isset($parsed['items']) || !is_array($parsed['items'])) {
    respondError('抽出結果を解釈できませんでした', 502);
}

/** 抽出できなかった項目は null のまま返し、画面では空欄にする */
function nullableText(mixed $value): ?string {
    $text = trim((string)($value ?? ''));
    return $text === '' ? null : $text;
}

function nullableInt(mixed $value): ?int {
    if ($value === null || $value === '') {
        return null;
    }
    return max(0, (int)$value);
}

$items = [];
foreach ($parsed['items'] as $item) {
    $name = nullableText($item['item_name'] ?? null);
    $quantity = nullableInt($item['quantity'] ?? null);
    $unitPrice = nullableInt($item['unit_price'] ?? null);
    $unit = nullableText($item['unit'] ?? null);

    if ($name === null && $quantity === null && $unitPrice === null && $unit === null) {
        continue;
    }

    $items[] = [
        'item_name' => $name,
        'quantity' => $quantity,
        'unit' => $unit,
        'unit_price' => $unitPrice,
    ];
}

echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
