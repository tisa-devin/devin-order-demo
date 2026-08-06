<?php
/**
 * 名刺画像から会社名・郵便番号・住所・電話番号を抽出して JSON で返すエンドポイント。
 * 抽出のみを行い、DBへの登録は行わない（画面側でユーザーが確認・修正して登録する）。
 */
require_once __DIR__ . '/../../config/api_keys.php';

const MAX_IMAGE_BYTES = 8 * 1024 * 1024;
const ALLOWED_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

header('Content-Type: application/json; charset=UTF-8');

function respondError(string $message, int $status): void {
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondError('POSTメソッドで送信してください', 405);
}

$apiKey = getApiKey('OPENAI_API_KEY');
if ($apiKey === null) {
    respondError('OpenAI APIキーが未設定です。config/api_keys.local.php を作成してください', 500);
}

$file = $_FILES['image'] ?? null;
if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    respondError('画像が送信されていません', 400);
}
if ($file['size'] > MAX_IMAGE_BYTES) {
    respondError('画像サイズが大きすぎます（上限8MB）', 400);
}

$mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
if (!in_array($mimeType, ALLOWED_MIME_TYPES, true)) {
    respondError('画像ファイル（JPEG/PNG/WebP/GIF）を送信してください', 400);
}

$dataUrl = 'data:' . $mimeType . ';base64,' . base64_encode(file_get_contents($file['tmp_name']));

$payload = [
    'model' => 'gpt-4o-mini',
    'temperature' => 0,
    'response_format' => ['type' => 'json_object'],
    'messages' => [
        [
            'role' => 'system',
            'content' => '名刺画像から会社情報を抽出するアシスタントです。JSONオブジェクトのみを返してください。'
                . 'キーは name（会社名・法人格を含む）、postal_code（ハイフン区切りの7桁。先頭の「〒」は除く）、'
                . 'address（都道府県から始まる住所。建物名・階数も含む）、tel（代表電話番号。ハイフン区切り）。'
                . '読み取れない項目は空文字にしてください。個人名・部署名・役職・メールアドレス・URLは含めないこと。',
        ],
        [
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => 'この名刺から会社名・郵便番号・住所・電話番号を抽出してください。'],
                ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
            ],
        ],
    ],
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
    respondError('OpenAI APIへの接続に失敗しました: ' . $curlError, 502);
}
if ($httpCode !== 200) {
    $decoded = json_decode($response, true);
    $apiMessage = $decoded['error']['message'] ?? '不明なエラー';
    respondError('OpenAI APIがエラーを返しました（HTTP ' . $httpCode . '）: ' . $apiMessage, 502);
}

$decoded = json_decode($response, true);
$content = $decoded['choices'][0]['message']['content'] ?? null;
$extracted = is_string($content) ? json_decode($content, true) : null;
if (!is_array($extracted)) {
    respondError('抽出結果を解析できませんでした', 502);
}

$result = [];
foreach (['name', 'postal_code', 'address', 'tel'] as $key) {
    $value = $extracted[$key] ?? '';
    $result[$key] = is_string($value) ? trim($value) : '';
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
