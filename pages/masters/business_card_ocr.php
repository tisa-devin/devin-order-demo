<?php
/**
 * 名刺画像から顧客情報を抽出するエンドポイント
 *
 * POST: card_image（画像ファイル）
 * 返却: {"name":..., "postal_code":..., "address":..., "tel":...} または {"error": "..."}
 * 顧客コードは業務側で採番するため抽出しない。
 */
require_once __DIR__ . '/../../config/api_keys.php';

header('Content-Type: application/json; charset=UTF-8');

const OCR_MAX_BYTES = 8 * 1024 * 1024;
const OCR_ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

function ocrError(string $message, int $status = 400): never {
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ocrError('POSTでリクエストしてください', 405);
}

$apiKey = getApiKey('OPENAI_API_KEY');
if ($apiKey === '') {
    ocrError('OpenAIのAPIキーが設定されていません（config/api_keys.local.php または環境変数 OPENAI_API_KEY）', 500);
}

if (($_FILES['card_image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    ocrError('名刺画像を送信してください');
}
if ($_FILES['card_image']['size'] > OCR_MAX_BYTES) {
    ocrError('画像サイズが大きすぎます（8MBまで）');
}

$mimeType = mime_content_type($_FILES['card_image']['tmp_name']);
if (!in_array($mimeType, OCR_ALLOWED_TYPES, true)) {
    ocrError('画像ファイル（JPEG/PNG/WebP/GIF）を送信してください');
}

$dataUri = 'data:' . $mimeType . ';base64,' . base64_encode(file_get_contents($_FILES['card_image']['tmp_name']));

$payload = [
    'model' => 'gpt-4o-mini',
    'response_format' => ['type' => 'json_object'],
    'messages' => [
        [
            'role' => 'system',
            'content' => '名刺画像から会社情報を抽出し、JSONのみを返してください。'
                . 'キーは name（会社名・法人格含む）, postal_code（ハイフン付き。例 100-0001）, address（都道府県から。郵便番号は含めない）, tel（代表電話。ハイフン付き）の4つです。'
                . '読み取れない項目は空文字にしてください。個人名や部署名はnameに含めないでください。',
        ],
        [
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => 'この名刺から会社名・郵便番号・住所・電話番号を抽出してください。'],
                ['type' => 'image_url', 'image_url' => ['url' => $dataUri]],
            ],
        ],
    ],
];

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 60,
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
    ocrError('OpenAI APIへの接続に失敗しました: ' . $curlError, 502);
}

$body = json_decode($response, true);
if ($httpCode !== 200) {
    ocrError('OpenAI APIがエラーを返しました: ' . ($body['error']['message'] ?? "HTTP $httpCode"), 502);
}

$extracted = json_decode($body['choices'][0]['message']['content'] ?? '', true);
if (!is_array($extracted)) {
    ocrError('抽出結果を解析できませんでした', 502);
}

$result = [];
foreach (['name', 'postal_code', 'address', 'tel'] as $field) {
    $result[$field] = trim((string)($extracted[$field] ?? ''));
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
