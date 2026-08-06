<?php
/**
 * 名刺画像から会社名・郵便番号・住所・電話番号をJSONで抽出するエンドポイント。
 * OpenAI Vision（gpt-4o-mini）を利用し、抽出結果を返すだけで登録は行わない。
 */
require_once __DIR__ . '/../../config/api_config.php';

const OCR_MODEL = 'gpt-4o-mini';
const OCR_MAX_BYTES = 10 * 1024 * 1024;
const OCR_ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

header('Content-Type: application/json; charset=UTF-8');

function respondError(string $message, int $status): void {
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondError('POSTメソッドで送信してください', 405);
}

$apiKey = getApiKey('openai_api_key');
if ($apiKey === '') {
    respondError('OpenAI APIキーが設定されていません（config/api_keys.php または環境変数 OPENAI_API_KEY）', 500);
}

$file = $_FILES['image'] ?? null;
if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    respondError('画像ファイルを送信してください', 400);
}
if ($file['size'] > OCR_MAX_BYTES) {
    respondError('画像サイズが大きすぎます（10MBまで）', 400);
}

$mimeType = mime_content_type($file['tmp_name']);
if (!in_array($mimeType, OCR_ALLOWED_MIME, true)) {
    respondError('対応していない画像形式です: ' . $mimeType, 400);
}

$imageData = file_get_contents($file['tmp_name']);
if ($imageData === false) {
    respondError('画像の読み込みに失敗しました', 500);
}
$dataUri = 'data:' . $mimeType . ';base64,' . base64_encode($imageData);

$prompt = <<<'TEXT'
名刺の画像から以下の項目を抽出し、JSONのみで返してください。
- name: 会社名（法人格を含む正式名称。個人事業なら屋号または氏名）
- postal_code: 郵便番号（例: 100-0001。読み取れなければ空文字）
- address: 住所（郵便番号を含めない。ビル名・階数まで含める）
- tel: 電話番号（代表番号を優先。ハイフン区切り。FAXは含めない）
読み取れない項目は空文字にしてください。推測で埋めないでください。
TEXT;

$payload = [
    'model' => OCR_MODEL,
    'response_format' => ['type' => 'json_object'],
    'temperature' => 0,
    'messages' => [
        [
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => $prompt],
                ['type' => 'image_url', 'image_url' => ['url' => $dataUri]],
            ],
        ],
    ],
];

$ch = curl_init('https://api.openai.com/v1/chat/completions');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
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
    respondError('OpenAI APIへの接続に失敗しました: ' . $curlError, 502);
}
if ($httpCode !== 200) {
    $decoded = json_decode($response, true);
    $apiMessage = $decoded['error']['message'] ?? '不明なエラー';
    respondError('OpenAI APIエラー（HTTP ' . $httpCode . '）: ' . $apiMessage, 502);
}

$decoded = json_decode($response, true);
$content = $decoded['choices'][0]['message']['content'] ?? '';
$extracted = json_decode($content, true);
if (!is_array($extracted)) {
    respondError('抽出結果の解析に失敗しました', 502);
}

$result = [];
foreach (['name', 'postal_code', 'address', 'tel'] as $field) {
    $result[$field] = trim((string)($extracted[$field] ?? ''));
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
