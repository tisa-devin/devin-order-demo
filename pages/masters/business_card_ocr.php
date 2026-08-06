<?php
/**
 * 名刺画像を OpenAI Vision に渡し、会社名・郵便番号・住所・電話番号をJSONで返すエンドポイント。
 * 抽出のみを行い、DBへは登録しない（呼び出し元のフォームに自動入力するだけ）。
 */
require_once __DIR__ . '/../../config/api_keys.php';

header('Content-Type: application/json; charset=UTF-8');

const MAX_IMAGE_BYTES = 8 * 1024 * 1024;
const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

function respondError(string $message, int $status = 400): never {
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondError('POSTのみ受け付けます', 405);
}

$apiKey = getOpenAiApiKey();
if ($apiKey === null) {
    respondError('OpenAI APIキーが未設定です。config/api_keys.local.php または環境変数 OPENAI_API_KEY を設定してください。', 500);
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    respondError('画像ファイルを送信してください');
}
if ($_FILES['image']['size'] > MAX_IMAGE_BYTES) {
    respondError('画像サイズが大きすぎます（上限8MB）');
}

$mimeType = mime_content_type($_FILES['image']['tmp_name']) ?: '';
if (!in_array($mimeType, ALLOWED_IMAGE_TYPES, true)) {
    respondError('対応していない画像形式です（JPEG / PNG / WebP / GIF）');
}

$dataUrl = 'data:' . $mimeType . ';base64,' . base64_encode(file_get_contents($_FILES['image']['tmp_name']));

$prompt = <<<PROMPT
これは日本のビジネス名刺の画像です。会社名・郵便番号・住所・電話番号を抽出してください。

制約:
- 出力は指定のJSONスキーマのみ。
- 会社名(name)は法人格を含む正式名称。個人名は含めない。
- 郵便番号(postal_code)は「123-4567」形式。ハイフンが無い場合は補う。
- 住所(address)は郵便番号を含めない。
- 電話番号(tel)は代表電話。FAXや携帯しか無い場合はその番号を使う。市外局番のハイフンは残す。
- 読み取れない項目は空文字にする。推測で埋めないこと。
PROMPT;

$payload = [
    'model' => getOpenAiVisionModel(),
    'temperature' => 0,
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
            'name' => 'business_card',
            'strict' => true,
            'schema' => [
                'type' => 'object',
                'additionalProperties' => false,
                'required' => ['name', 'postal_code', 'address', 'tel'],
                'properties' => [
                    'name' => ['type' => 'string'],
                    'postal_code' => ['type' => 'string'],
                    'address' => ['type' => 'string'],
                    'tel' => ['type' => 'string'],
                ],
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
$httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($response === false) {
    respondError('OpenAI APIへの接続に失敗しました: ' . $curlError, 502);
}

$body = json_decode($response, true);
if ($httpStatus !== 200) {
    respondError('OpenAI APIがエラーを返しました: ' . ($body['error']['message'] ?? ('HTTP ' . $httpStatus)), 502);
}

$extracted = json_decode($body['choices'][0]['message']['content'] ?? '', true);
if (!is_array($extracted)) {
    respondError('抽出結果を解釈できませんでした', 502);
}

echo json_encode([
    'name' => (string)($extracted['name'] ?? ''),
    'postal_code' => (string)($extracted['postal_code'] ?? ''),
    'address' => (string)($extracted['address'] ?? ''),
    'tel' => (string)($extracted['tel'] ?? ''),
], JSON_UNESCAPED_UNICODE);
