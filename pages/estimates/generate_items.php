<?php
/**
 * 自然文の要望から見積明細（item_name / quantity / unit / unit_price / tax_rate）を
 * OpenAI（gpt-4o-mini）で生成するエンドポイント。JSONのみ返す（DB保存なし）。
 *
 * リクエスト: POST prompt=<自然文>
 * レスポンス: {"success": true, "items": [{"item_name","quantity","unit","unit_price","tax_rate"}, ...]}
 *             {"success": false, "error": "..."}
 */
require_once __DIR__ . '/../../config/openai.php';

header('Content-Type: application/json; charset=utf-8');

function jsonError(string $message, int $status = 400): void {
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonError('POSTメソッドでリクエストしてください', 405);
}

$apiKey = getOpenAiApiKey();
if ($apiKey === '') {
    jsonError('OpenAI APIキーが設定されていません（環境変数 OPENAI_API_KEY または config/api_keys.local.php を設定してください）', 500);
}

$promptText = trim($_POST['prompt'] ?? '');
if ($promptText === '') {
    jsonError('要望テキストを入力してください');
}
if (mb_strlen($promptText) > 2000) {
    jsonError('要望テキストが長すぎます（最大2000文字）');
}

$instruction = <<<PROMPT
あなたは日本の受発注管理システムの見積作成アシスタントです。
ユーザーの自然文の要望から、見積の明細行を作成してください。
出力は必ず次の形式のJSONオブジェクトのみとし、余計な説明は含めないでください。
{"items": [{"item_name": string, "quantity": number, "unit": string, "unit_price": number, "tax_rate": number}, ...]}
ルール:
- item_name: 作業・品目名（日本語、簡潔に）。
- quantity: 数量（1以上の整数）。
- unit: 単位（例: 式, 人月, 人日, 個, 時間）。
- unit_price: 単価（税抜、円、整数）。金額の合計目安が示されている場合は、明細に按分して単価を設定する。
- tax_rate: 消費税率。10 または 8 のいずれか（通常は10）。
- 要望に金額の目安（例「100万円くらい」）があれば、各行の quantity × unit_price の合計がその目安に近くなるようにする。
- 明細は1〜10行程度。
PROMPT;

$payload = [
    'model' => OPENAI_VISION_MODEL,
    'temperature' => 0.2,
    'response_format' => ['type' => 'json_object'],
    'messages' => [
        ['role' => 'system', 'content' => $instruction],
        ['role' => 'user', 'content' => $promptText],
    ],
];

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey,
    ],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    CURLOPT_TIMEOUT => 60,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($response === false) {
    jsonError('OpenAI APIへの接続に失敗しました: ' . $curlErr, 502);
}
if ($httpCode < 200 || $httpCode >= 300) {
    $detail = json_decode($response, true);
    $msg = $detail['error']['message'] ?? ('HTTP ' . $httpCode);
    jsonError('OpenAI APIエラー: ' . $msg, 502);
}

$decoded = json_decode($response, true);
$content = $decoded['choices'][0]['message']['content'] ?? '';
$parsed = json_decode($content, true);
if (!is_array($parsed) || !isset($parsed['items']) || !is_array($parsed['items'])) {
    jsonError('生成結果の解析に失敗しました', 502);
}

$items = [];
foreach ($parsed['items'] as $row) {
    if (!is_array($row)) {
        continue;
    }
    $name = trim((string)($row['item_name'] ?? ''));
    if ($name === '') {
        continue;
    }
    $qty = (int)round((float)($row['quantity'] ?? 1));
    if ($qty < 1) {
        $qty = 1;
    }
    $unit = trim((string)($row['unit'] ?? '式'));
    if ($unit === '') {
        $unit = '式';
    }
    $unitPrice = (int)round((float)($row['unit_price'] ?? 0));
    if ($unitPrice < 0) {
        $unitPrice = 0;
    }
    $taxRate = (int)($row['tax_rate'] ?? 10);
    if ($taxRate !== 8) {
        $taxRate = 10;
    }
    $items[] = [
        'item_name' => $name,
        'quantity' => $qty,
        'unit' => $unit,
        'unit_price' => $unitPrice,
        'tax_rate' => $taxRate,
    ];
}

if (empty($items)) {
    jsonError('明細を生成できませんでした。要望を具体的にして再度お試しください', 502);
}

echo json_encode(['success' => true, 'items' => $items], JSON_UNESCAPED_UNICODE);
