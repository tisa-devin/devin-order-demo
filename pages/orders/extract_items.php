<?php
require_once __DIR__ . '/../../config/openai.php';

header('Content-Type: application/json; charset=UTF-8');

const MAX_IMAGE_BYTES = 10 * 1024 * 1024;
const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];

function respondError(string $message, int $status = 400): never {
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respondError('POSTでリクエストしてください', 405);
}

$file = $_FILES['image'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    respondError('画像のアップロードに失敗しました');
}
if ($file['size'] > MAX_IMAGE_BYTES) {
    respondError('画像サイズが大きすぎます（10MBまで）');
}

$mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
if (!in_array($mimeType, ALLOWED_IMAGE_TYPES, true)) {
    respondError('対応していないファイル形式です（JPEG / PNG / GIF / WebP）');
}

try {
    $result = extractOrderItemsFromImage(file_get_contents($file['tmp_name']), $mimeType);
} catch (RuntimeException $e) {
    respondError($e->getMessage(), 500);
}

echo json_encode($result, JSON_UNESCAPED_UNICODE);
