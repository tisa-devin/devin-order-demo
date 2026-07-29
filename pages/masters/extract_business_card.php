<?php
/**
 * 名刺画像から会社名・郵便番号・住所・電話番号を抽出する AJAX エンドポイント。
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
        'name' => ['type' => 'string'],
        'postal_code' => ['type' => 'string'],
        'address' => ['type' => 'string'],
        'tel' => ['type' => 'string'],
    ],
    'required' => ['name', 'postal_code', 'address', 'tel'],
    'additionalProperties' => false,
];

$payload = [
    'model' => 'gpt-4o-mini',
    'temperature' => 0,
    'messages' => [
        [
            'role' => 'system',
            'content' => '名刺画像から取引先情報を抽出するアシスタント。会社名（name）、郵便番号（postal_code、「123-4567」形式、〒記号は含めない）、住所（address、郵便番号を含めない）、電話番号（tel、ハイフン区切り。FAXや携帯しか無い場合を除き代表電話を優先）を読み取る。読み取れない項目は空文字にする。個人名・部署名・役職・メールアドレス・URLは含めない。',
        ],
        [
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => 'この名刺から会社名・郵便番号・住所・電話番号を抽出してください。'],
                ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
            ],
        ],
    ],
    'response_format' => [
        'type' => 'json_schema',
        'json_schema' => ['name' => 'business_card', 'strict' => true, 'schema' => $schema],
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
if (!is_array($parsed)) {
    respondError('抽出結果を解釈できませんでした', 502);
}

$customer = [
    'name' => trim((string)($parsed['name'] ?? '')),
    'postal_code' => trim((string)($parsed['postal_code'] ?? '')),
    'address' => trim((string)($parsed['address'] ?? '')),
    'tel' => trim((string)($parsed['tel'] ?? '')),
];
$customer['postal_code'] = ltrim($customer['postal_code'], '〒 ');

echo json_encode(['customer' => $customer], JSON_UNESCAPED_UNICODE);
