<?php
/**
 * 注文書画像から明細を抽出して JSON で返すエンドポイント。
 * 受注編集画面（edit.php）から fetch で呼び出される。
 */
require_once __DIR__ . '/../../config/openai.php';

header('Content-Type: application/json; charset=UTF-8');

/** エラーレスポンスを返して終了する */
function respondError(string $message, int $status): void
{
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondError('POSTで呼び出してください。', 405);
}

$file = $_FILES['image'] ?? null;
if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    respondError('画像がアップロードされていません。', 400);
}

$mimeType = mime_content_type($file['tmp_name']) ?: '';

try {
    $items = extractOrderItemsFromImage($file['tmp_name'], $mimeType);
} catch (OpenAiException $e) {
    respondError($e->getMessage(), 502);
}

echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
