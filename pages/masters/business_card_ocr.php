<?php
/**
 * 名刺画像から会社名・郵便番号・住所・電話番号を OpenAI Vision で抽出するエンドポイント。
 * 抽出結果を JSON で返すのみ（DBへは登録しない。登録は画面側で確認・修正後に既存フォームで行う）。
 *
 * リクエスト: POST multipart/form-data, フィールド名 image（画像ファイル）
 * レスポンス: {"success": true, "data": {"name","postal_code","address","tel"}}
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
    jsonError('POSTメソッドで画像を送信してください', 405);
}

$apiKey = getOpenAiApiKey();
if ($apiKey === '') {
    jsonError('OpenAI APIキーが設定されていません（config/api_keys.local.php または環境変数 OPENAI_API_KEY を設定してください）', 500);
}

if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    jsonError('画像ファイルをアップロードしてください');
}

$tmpPath = $_FILES['image']['tmp_name'];
$mime = mime_content_type($tmpPath) ?: 'image/jpeg';
if (strpos($mime, 'image/') !== 0) {
    jsonError('画像ファイルを指定してください');
}

$maxBytes = 10 * 1024 * 1024; // 10MB
if (filesize($tmpPath) > $maxBytes) {
    jsonError('画像サイズが大きすぎます（最大10MB）');
}

$imageData = file_get_contents($tmpPath);
if ($imageData === false) {
    jsonError('画像の読み込みに失敗しました', 500);
}
$dataUri = 'data:' . $mime . ';base64,' . base64_encode($imageData);

$prompt = <<<PROMPT
この画像は名刺です。以下の項目を読み取り、JSONオブジェクトで返してください。
- name: 会社名（company name）。会社名が見当たらない場合は個人名。
- postal_code: 郵便番号（例 123-4567）。
- address: 住所。
- tel: 電話番号（FAXではなく代表またはTELを優先）。
読み取れない項目は空文字 "" にしてください。余計な説明やキーは含めず、上記4キーのみのJSONを返してください。
PROMPT;

$payload = [
    'model' => OPENAI_VISION_MODEL,
    'temperature' => 0,
    'response_format' => ['type' => 'json_object'],
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
$extracted = json_decode($content, true);
if (!is_array($extracted)) {
    jsonError('抽出結果の解析に失敗しました', 502);
}

$data = [
    'name' => trim((string)($extracted['name'] ?? '')),
    'postal_code' => trim((string)($extracted['postal_code'] ?? '')),
    'address' => trim((string)($extracted['address'] ?? '')),
    'tel' => trim((string)($extracted['tel'] ?? '')),
];

echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
