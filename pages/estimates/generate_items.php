<?php
/**
 * 自然文の要件から見積明細を生成する AJAX エンドポイント。
 * 生成結果を返すだけで、DBへの保存は行わない。
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

$prompt = trim((string)($_POST['prompt'] ?? ''));
if ($prompt === '') {
    respondError('要件テキストを入力してください');
}
if (mb_strlen($prompt) > 2000) {
    respondError('要件テキストが長すぎます（2000文字以内）');
}

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
                    'tax_rate' => ['type' => 'integer', 'enum' => [8, 10]],
                ],
                'required' => ['item_name', 'quantity', 'unit', 'unit_price', 'tax_rate'],
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
            'content' => '日本のシステム開発会社の見積明細を作成するアシスタント。自然文の要件から見積明細を3〜8行程度に分解し、品名（item_name）、数量（quantity、1以上の整数）、単位（unit、「式」「人月」「ヶ月」など）、税抜単価（unit_price、整数の円）、税率（tax_rate、8または10。軽減税率対象でなければ10）を出力する。金額の目安が示されている場合は、明細の合計（数量×単価の総和）が税抜でその金額に概ね一致するようにする。合計行・小計行・消費税行は明細に含めない。',
        ],
        ['role' => 'user', 'content' => $prompt],
    ],
    'response_format' => [
        'type' => 'json_schema',
        'json_schema' => ['name' => 'estimate_items', 'strict' => true, 'schema' => $schema],
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

$parsed = json_decode($body['choices'][0]['message']['content'] ?? '', true);
if (!is_array($parsed) || !isset($parsed['items']) || !is_array($parsed['items'])) {
    respondError('生成結果を解釈できませんでした', 502);
}

$items = [];
foreach ($parsed['items'] as $item) {
    $name = trim((string)($item['item_name'] ?? ''));
    if ($name === '') {
        continue;
    }
    $taxRate = (int)($item['tax_rate'] ?? 10);
    $items[] = [
        'item_name' => $name,
        'quantity' => max(1, (int)($item['quantity'] ?? 1)),
        'unit' => trim((string)($item['unit'] ?? '')) ?: '式',
        'unit_price' => max(0, (int)($item['unit_price'] ?? 0)),
        'tax_rate' => in_array($taxRate, [8, 10], true) ? $taxRate : 10,
    ];
}

echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
