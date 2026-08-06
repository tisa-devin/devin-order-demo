<?php
// 名刺画像から会社名・郵便番号・住所・電話番号を抽出して JSON で返すエンドポイント。
require_once __DIR__ . '/../../config/api_keys.php';

const CARD_MAX_FILE_SIZE = 8 * 1024 * 1024;
const CARD_ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
const CARD_OPENAI_MODEL = 'gpt-4o-mini';

header('Content-Type: application/json; charset=UTF-8');

function respondError(int $status, string $message): never {
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function normalizePostalCode(string $value): string {
    $digits = preg_replace('/\D/', '', mb_convert_kana($value, 'n'));
    if (strlen($digits) === 7) {
        return substr($digits, 0, 3) . '-' . substr($digits, 3);
    }
    return trim($value);
}

function normalizeTel(string $value): string {
    return trim(mb_convert_kana($value, 'a'));
}

function extractCardFields(string $imageData, string $mimeType, string $apiKey): array {
    $payload = [
        'model' => CARD_OPENAI_MODEL,
        'max_tokens' => 300,
        'messages' => [
            [
                'role' => 'system',
                'content' => '名刺画像から会社名・郵便番号・住所・電話番号を抽出し、指定のJSONスキーマで返してください。読み取れない項目は空文字にしてください。会社名は法人格を含む正式名称、住所は都道府県から番地・ビル名までを1行にまとめてください。',
            ],
            [
                'role' => 'user',
                'content' => [
                    ['type' => 'text', 'text' => 'この名刺から項目を抽出してください。'],
                    [
                        'type' => 'image_url',
                        'image_url' => ['url' => 'data:' . $mimeType . ';base64,' . base64_encode($imageData)],
                    ],
                ],
            ],
        ],
        'response_format' => [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'business_card',
                'strict' => true,
                'schema' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'postal_code' => ['type' => 'string'],
                        'address' => ['type' => 'string'],
                        'tel' => ['type' => 'string'],
                    ],
                    'required' => ['name', 'postal_code', 'address', 'tel'],
                    'additionalProperties' => false,
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
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        respondError(502, 'OpenAI APIへの接続に失敗しました: ' . $curlError);
    }

    $body = json_decode($response, true);
    if ($status !== 200) {
        $apiMessage = $body['error']['message'] ?? '不明なエラー';
        respondError(502, "OpenAI APIエラー (HTTP $status): $apiMessage");
    }

    $content = $body['choices'][0]['message']['content'] ?? '';
    $fields = json_decode($content, true);
    if (!is_array($fields)) {
        respondError(502, '抽出結果を解釈できませんでした');
    }

    return [
        'name' => trim((string)($fields['name'] ?? '')),
        'postal_code' => normalizePostalCode((string)($fields['postal_code'] ?? '')),
        'address' => trim((string)($fields['address'] ?? '')),
        'tel' => normalizeTel((string)($fields['tel'] ?? '')),
    ];
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondError(405, 'POSTメソッドのみ受け付けます');
}

$file = $_FILES['card_image'] ?? null;
if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    respondError(400, '画像が選択されていません');
}
if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
    respondError(400, '画像サイズが大きすぎます');
}
if ($file['error'] !== UPLOAD_ERR_OK) {
    respondError(400, '画像のアップロードに失敗しました（エラーコード: ' . $file['error'] . '）');
}
if ($file['size'] > CARD_MAX_FILE_SIZE) {
    respondError(400, '画像サイズが大きすぎます（上限8MB）');
}

$mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
if (!in_array($mimeType, CARD_ALLOWED_MIME, true)) {
    respondError(400, '対応していない画像形式です（JPEG/PNG/WebP/GIF）');
}

$apiKey = getApiKey('OPENAI_API_KEY');
if ($apiKey === null) {
    respondError(500, 'OpenAI APIキーが設定されていません（config/api_keys.local.php または環境変数 OPENAI_API_KEY）');
}

$imageData = file_get_contents($file['tmp_name']);
if ($imageData === false) {
    respondError(500, '画像の読み込みに失敗しました');
}

echo json_encode([
    'ok' => true,
    'data' => extractCardFields($imageData, $mimeType, $apiKey),
], JSON_UNESCAPED_UNICODE);
