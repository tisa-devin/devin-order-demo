<?php
/**
 * 注文書画像から明細を抽出してJSONで返すエンドポイント（受注編集画面から fetch で呼ばれる）
 */
require_once __DIR__ . '/../../config/openai.php';

header('Content-Type: application/json; charset=utf-8');

const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

function respondError(string $message, int $status = 400): never {
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondError('POSTでリクエストしてください', 405);
}
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    respondError('画像ファイルを選択してください');
}

$mimeType = mime_content_type($_FILES['image']['tmp_name']) ?: '';
if (!in_array($mimeType, ALLOWED_IMAGE_TYPES, true)) {
    respondError('対応していないファイル形式です（JPEG / PNG / WebP / GIF）');
}

try {
    $items = extractOrderItemsFromImage($_FILES['image']['tmp_name'], $mimeType);
} catch (RuntimeException $e) {
    respondError($e->getMessage(), 500);
}

echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE);
