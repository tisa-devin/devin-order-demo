<?php
// 名刺画像を OpenAI Vision API に送り、会社名・郵便番号・住所・電話番号を抽出して JSON で返す。
require_once __DIR__ . '/../../config/openai.php';

header('Content-Type: application/json; charset=utf-8');

function respondError(string $message, int $status = 400): void {
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondError('POSTメソッドでリクエストしてください', 405);
}

$apiKey = getOpenAIApiKey();
if ($apiKey === '') {
    respondError('OpenAI APIキーが設定されていません。config/config.php を作成するか環境変数 OPENAI_API_KEY を設定してください。', 500);
}

if (!isset($_FILES['image']) || !is_array($_FILES['image'])) {
    respondError('画像が送信されていません');
}

$file = $_FILES['image'];
if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
        respondError('画像サイズが大きすぎます');
    }
    respondError('画像のアップロードに失敗しました');
}

$maxBytes = 8 * 1024 * 1024;
if ($file['size'] > $maxBytes) {
    respondError('画像サイズが大きすぎます（8MBまで）');
}

if (!is_uploaded_file($file['tmp_name'])) {
    respondError('不正なアップロードです');
}

$imageInfo = @getimagesize($file['tmp_name']);
$allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
if ($imageInfo === false || !in_array($imageInfo['mime'], $allowedMimes, true)) {
    respondError('対応していない画像形式です（JPEG/PNG/WebP/GIF）');
}

$binary = file_get_contents($file['tmp_name']);
if ($binary === false) {
    respondError('画像の読み込みに失敗しました', 500);
}
$dataUrl = 'data:' . $imageInfo['mime'] . ';base64,' . base64_encode($binary);

$prompt = <<<PROMPT
これは名刺の画像です。名刺から会社名・郵便番号・住所・電話番号を読み取り、次のキーを持つJSONオブジェクトのみを返してください。
- company_name: 会社名（法人格を含む正式名称）
- postal_code: 郵便番号（ハイフン区切りの数字のみ。例: 100-0001）
- address: 住所（郵便番号を含めない）
- tel: 電話番号（FAXや携帯ではなく代表電話を優先。ハイフン区切り）
読み取れない項目は空文字列にしてください。推測で値を作らないでください。
PROMPT;

$payload = [
    'model' => getOpenAIModel(),
    'temperature' => 0,
    'response_format' => ['type' => 'json_object'],
    'messages' => [
        [
            'role' => 'system',
            'content' => '与えられた名刺画像から情報を抽出し、指定されたJSON形式のみで回答するアシスタントです。',
        ],
        [
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => $prompt],
                ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
            ],
        ],
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
    CURLOPT_TIMEOUT => 120,
]);
$response = curl_exec($ch);
$curlError = curl_error($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response === false) {
    respondError('OpenAI APIへの接続に失敗しました: ' . $curlError, 502);
}

$decoded = json_decode($response, true);
if (!is_array($decoded)) {
    respondError('OpenAI APIのレスポンスを解析できませんでした', 502);
}
if ($httpCode >= 400) {
    $apiMessage = $decoded['error']['message'] ?? 'HTTP ' . $httpCode;
    respondError('OpenAI APIエラー: ' . $apiMessage, 502);
}

$content = $decoded['choices'][0]['message']['content'] ?? '';
$extracted = json_decode($content, true);
if (!is_array($extracted)) {
    respondError('名刺の情報を抽出できませんでした', 502);
}

echo json_encode([
    'company_name' => trim((string)($extracted['company_name'] ?? '')),
    'postal_code' => trim((string)($extracted['postal_code'] ?? '')),
    'address' => trim((string)($extracted['address'] ?? '')),
    'tel' => trim((string)($extracted['tel'] ?? '')),
], JSON_UNESCAPED_UNICODE);
