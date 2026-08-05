<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json; charset=utf-8');

function respond(int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['error' => 'POSTメソッドで呼び出してください']);
}

$apiKey = getOpenAiApiKey();
if ($apiKey === '') {
    respond(500, ['error' => 'OpenAI APIキーが設定されていません（config/secrets.php を確認してください）']);
}

$file = $_FILES['card_image'] ?? null;
if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    respond(400, ['error' => '画像ファイルがアップロードされていません']);
}
if (!is_uploaded_file($file['tmp_name'])) {
    respond(400, ['error' => '不正なアップロードです']);
}

const MAX_UPLOAD_BYTES = 10 * 1024 * 1024;
if ($file['size'] > MAX_UPLOAD_BYTES) {
    respond(400, ['error' => '画像サイズが大きすぎます（上限10MB）']);
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
$allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
if (!is_string($mime) || !in_array($mime, $allowed, true)) {
    respond(400, ['error' => '対応していない画像形式です（JPEG/PNG/WebP/GIF）']);
}

$binary = file_get_contents($file['tmp_name']);
if ($binary === false) {
    respond(500, ['error' => '画像の読み込みに失敗しました']);
}
$dataUrl = 'data:' . $mime . ';base64,' . base64_encode($binary);

$prompt = '名刺画像から 会社名(company_name)・郵便番号(postal_code)・住所(address)・電話番号(tel) を抽出し、'
    . 'これらのキーのみを持つJSONだけを返してください。読み取れない項目は空文字にしてください。'
    . '顧客コードなど他の項目は含めないでください。';

$payload = [
    'model' => 'gpt-4o-mini',
    'response_format' => ['type' => 'json_object'],
    'messages' => [[
        'role' => 'user',
        'content' => [
            ['type' => 'text', 'text' => $prompt],
            ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
        ],
    ]],
];

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
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
    respond(502, ['error' => 'OpenAI APIへの接続に失敗しました: ' . $curlError]);
}
if ($httpCode < 200 || $httpCode >= 300) {
    respond(502, ['error' => 'OpenAI APIがエラーを返しました (HTTP ' . $httpCode . ')']);
}

$decoded = json_decode($response, true);
$content = $decoded['choices'][0]['message']['content'] ?? null;
if (!is_string($content)) {
    respond(502, ['error' => 'OpenAI APIのレスポンス形式が不正です']);
}

$extracted = json_decode($content, true);
if (!is_array($extracted)) {
    respond(502, ['error' => '抽出結果のJSONパースに失敗しました']);
}

respond(200, [
    'company_name' => (string)($extracted['company_name'] ?? ''),
    'postal_code' => (string)($extracted['postal_code'] ?? ''),
    'address' => (string)($extracted['address'] ?? ''),
    'tel' => (string)($extracted['tel'] ?? ''),
]);
