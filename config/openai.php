<?php
/**
 * OpenAI APIの設定とVisionによる明細抽出。
 * APIキーは環境変数 OPENAI_API_KEY から読み込む（config/.env でも指定可・.gitignore対象）。
 */

const OPENAI_MODEL = 'gpt-4o-mini';
const OPENAI_ENDPOINT = 'https://api.openai.com/v1/chat/completions';
const OPENAI_MAX_IMAGE_BYTES = 8 * 1024 * 1024;

/**
 * config/.env が存在すれば KEY=VALUE 形式で環境変数に読み込む
 */
function loadEnvFile(string $path = __DIR__ . '/.env'): void {
    static $loaded = [];
    if (isset($loaded[$path]) || !is_readable($path)) {
        return;
    }
    $loaded[$path] = true;

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'") && $value[0] === substr($value, -1)) {
            $value = substr($value, 1, -1);
        }
        if ($name !== '' && getenv($name) === false) {
            putenv("$name=$value");
            $_ENV[$name] = $value;
        }
    }
}

/**
 * APIキーを取得する。未設定の場合は例外。
 */
function getOpenAiApiKey(): string {
    loadEnvFile();
    $key = getenv('OPENAI_API_KEY');
    if ($key === false || trim($key) === '') {
        $key = $_ENV['OPENAI_API_KEY'] ?? '';
    }
    $key = trim((string)$key);
    if ($key === '') {
        throw new RuntimeException('OPENAI_API_KEY が設定されていません（環境変数または config/.env に設定してください）');
    }
    return $key;
}

function isOpenAiConfigured(): bool {
    try {
        getOpenAiApiKey();
        return true;
    } catch (RuntimeException) {
        return false;
    }
}

/**
 * 抽出値を整数に正規化する。読み取れない場合は空文字を返す。
 */
function normalizeExtractedInt(mixed $value): int|string {
    if ($value === null) {
        return '';
    }
    $digits = preg_replace('/[^0-9]/', '', (string)$value);
    return $digits === '' ? '' : (int)$digits;
}

/**
 * 注文書画像から明細（品名・数量・単位・単価）を抽出する
 *
 * 読み取れなかった項目は空文字のまま返す（画面側で空欄表示）
 *
 * @return array<int, array{item_name: string, quantity: int|string, unit: string, unit_price: int|string}>
 */
function extractOrderItemsFromImage(string $imagePath, string $mimeType): array {
    $apiKey = getOpenAiApiKey();

    $bytes = file_get_contents($imagePath);
    if ($bytes === false) {
        throw new RuntimeException('画像ファイルを読み込めませんでした');
    }
    if (strlen($bytes) > OPENAI_MAX_IMAGE_BYTES) {
        throw new RuntimeException('画像サイズが大きすぎます（上限8MB）');
    }

    $prompt = <<<'PROMPT'
あなたは注文書の読み取りアシスタントです。画像は注文書です。
明細行（品目）を読み取り、次のJSONだけを返してください。説明文は不要です。

{"items":[{"item_name":"品名","quantity":1,"unit":"式","unit_price":10000}]}

ルール:
- quantity と unit_price は数値のみ（カンマ・通貨記号・小数点は除去し整数にする）
- 読み取れない項目は推測せず null とする（quantity / unit / unit_price）
- 明細表の行番号（No）が振られている行は、数量・単位・単価が空欄でも必ずすべて出力する。行を省略してはいけない
- 単価が不明で金額と数量のみ判る場合は unit_price = 金額 ÷ 数量 とする
- 小計・消費税・合計などの集計行は含めない
- 明細が読み取れない場合は {"items":[]} を返す
PROMPT;

    $payload = [
        'model' => OPENAI_MODEL,
        'response_format' => ['type' => 'json_object'],
        'messages' => [[
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => $prompt],
                ['type' => 'image_url', 'image_url' => [
                    'url' => 'data:' . $mimeType . ';base64,' . base64_encode($bytes),
                ]],
            ],
        ]],
    ];

    $ch = curl_init(OPENAI_ENDPOINT);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
    ]);
    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('OpenAI APIへの接続に失敗しました: ' . $curlError);
    }
    $decoded = json_decode($response, true);
    if ($status !== 200) {
        $apiMessage = $decoded['error']['message'] ?? '不明なエラー';
        throw new RuntimeException('OpenAI APIエラー（HTTP ' . $status . '）: ' . $apiMessage);
    }

    $content = $decoded['choices'][0]['message']['content'] ?? '';
    $parsed = json_decode($content, true);
    if (!is_array($parsed) || !isset($parsed['items']) || !is_array($parsed['items'])) {
        throw new RuntimeException('抽出結果を解釈できませんでした');
    }

    $items = [];
    foreach ($parsed['items'] as $item) {
        if (!is_array($item)) {
            continue;
        }
        $name = trim((string)($item['item_name'] ?? ''));
        if ($name === '') {
            continue;
        }
        $items[] = [
            'item_name' => $name,
            'quantity' => normalizeExtractedInt($item['quantity'] ?? null),
            'unit' => trim((string)($item['unit'] ?? '')),
            'unit_price' => normalizeExtractedInt($item['unit_price'] ?? null),
        ];
    }

    return $items;
}
