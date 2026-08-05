<?php
$pageTitle = '顧客マスタ';
require_once __DIR__ . '/../../includes/header.php';

$pdo = getDB();
$message = '';
$error = '';

// CSVインポートの取り込み対象列（先頭2列は必須）
const IMPORT_COLUMNS = ['code', 'name', 'postal_code', 'address', 'tel', 'accounting_code'];

/**
 * アップロードされたCSVを1行ずつ検証し、取り込み可能な行とエラー行に振り分ける
 *
 * @return array ['rows' => 取り込み対象行, 'errors' => ['line' => 行番号, 'reason' => 理由]]
 */
function parseCustomerCsv(string $path, PDO $pdo): array {
    $existingCodes = $pdo->query("SELECT code FROM customers")->fetchAll(PDO::FETCH_COLUMN);
    $existingCodes = array_flip($existingCodes);

    $rows = [];
    $errors = [];
    $seenCodes = [];

    $contents = file_get_contents($path);
    // Excel由来のSJISもUTF-8のBOM付きも受け付ける
    $contents = preg_replace('/^\xEF\xBB\xBF/', '', $contents);
    if (!mb_check_encoding($contents, 'UTF-8')) {
        $contents = mb_convert_encoding($contents, 'UTF-8', 'SJIS-win');
    }

    $handle = fopen('php://temp', 'r+');
    fwrite($handle, $contents);
    rewind($handle);

    $lineNo = 0;
    while (($line = fgetcsv($handle, 0, ',', '"', '')) !== false) {
        $lineNo++;
        if ($lineNo === 1 && trim((string)($line[0] ?? '')) === IMPORT_COLUMNS[0]) {
            continue; // 見出し行
        }
        if (count(array_filter($line, fn($value) => trim((string)$value) !== '')) === 0) {
            continue; // 空行
        }

        if (count($line) < 2) {
            $errors[] = ['line' => $lineNo, 'reason' => '列が不足しています（顧客コードと顧客名の2列以上が必要です）'];
            continue;
        }

        $values = [];
        foreach (IMPORT_COLUMNS as $index => $column) {
            $values[$column] = trim((string)($line[$index] ?? ''));
        }

        if ($values['code'] === '' || $values['name'] === '') {
            $errors[] = ['line' => $lineNo, 'reason' => '顧客コードと顧客名は必須です'];
            continue;
        }
        if (isset($seenCodes[$values['code']])) {
            $errors[] = ['line' => $lineNo, 'reason' => "顧客コード {$values['code']} がCSV内で重複しています（{$seenCodes[$values['code']]}行目と同じ）"];
            continue;
        }

        $seenCodes[$values['code']] = $lineNo;
        $values['line'] = $lineNo;
        $values['mode'] = isset($existingCodes[$values['code']]) ? 'update' : 'insert';
        $rows[] = $values;
    }
    fclose($handle);

    return ['rows' => $rows, 'errors' => $errors];
}

$importRows = [];
$importErrors = [];
$importResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create' || $action === 'update') {
        $id = $_POST['id'] ?? null;
        $code = trim($_POST['code'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $postal_code = trim($_POST['postal_code'] ?? '');
        $address = trim($_POST['address'] ?? '');
        $tel = trim($_POST['tel'] ?? '');
        $accounting_code = trim($_POST['accounting_code'] ?? '');
        
        if (empty($code) || empty($name)) {
            $error = '顧客コードと顧客名は必須です';
        } else {
            try {
                if ($action === 'create') {
                    $stmt = $pdo->prepare("INSERT INTO customers (code, name, postal_code, address, tel, accounting_code) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$code, $name, $postal_code, $address, $tel, $accounting_code]);
                    $message = '顧客を登録しました';
                } else {
                    $stmt = $pdo->prepare("UPDATE customers SET code = ?, name = ?, postal_code = ?, address = ?, tel = ?, accounting_code = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$code, $name, $postal_code, $address, $tel, $accounting_code, $id]);
                    $message = '顧客を更新しました';
                }
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'UNIQUE') !== false) {
                    $error = 'この顧客コードは既に使用されています';
                } else {
                    $error = 'エラーが発生しました: ' . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'import_preview') {
        if (($_FILES['csv_file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $error = 'CSVファイルを選択してください';
        } else {
            ['rows' => $importRows, 'errors' => $importErrors] = parseCustomerCsv($_FILES['csv_file']['tmp_name'], $pdo);
            if (!$importRows && !$importErrors) {
                $error = '取り込める行がありませんでした';
            }
        }
    } elseif ($action === 'import_commit') {
        $importRows = json_decode($_POST['rows'] ?? '[]', true) ?: [];
        $importErrors = json_decode($_POST['errors'] ?? '[]', true) ?: [];
        $inserted = 0;
        $updated = 0;

        $insertStmt = $pdo->prepare("INSERT INTO customers (code, name, postal_code, address, tel, accounting_code) VALUES (?, ?, ?, ?, ?, ?)");
        $updateStmt = $pdo->prepare("UPDATE customers SET name = ?, postal_code = ?, address = ?, tel = ?, accounting_code = ?, updated_at = CURRENT_TIMESTAMP WHERE code = ?");

        $pdo->beginTransaction();
        foreach ($importRows as $row) {
            // 確認画面の表示中に他の操作で追加された場合に備え、確定時にも存在確認する
            $exists = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE code = ?");
            $exists->execute([$row['code']]);
            if ((int)$exists->fetchColumn() > 0) {
                $updateStmt->execute([$row['name'], $row['postal_code'], $row['address'], $row['tel'], $row['accounting_code'], $row['code']]);
                $updated++;
            } else {
                $insertStmt->execute([$row['code'], $row['name'], $row['postal_code'], $row['address'], $row['tel'], $row['accounting_code']]);
                $inserted++;
            }
        }
        $pdo->commit();

        $importResult = ['inserted' => $inserted, 'updated' => $updated, 'skipped' => count($importErrors)];
        $importRows = [];
        $message = "CSVを取り込みました（追加{$inserted}件 / 更新{$updated}件 / スキップ" . count($importErrors) . "件）";
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? null;
        try {
            $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
            $stmt->execute([$id]);
            $message = '顧客を削除しました';
        } catch (PDOException $e) {
            $error = '削除できません（関連データが存在します）';
        }
    }
}

$editCustomer = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editCustomer = $stmt->fetch();
}

$stmt = $pdo->query("SELECT * FROM customers ORDER BY code");
$customers = $stmt->fetchAll();
?>

<h2 class="mb-4"><i class="bi bi-people"></i> 顧客マスタ</h2>

<?php if ($message): ?>
<div class="alert alert-success"><?= h($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header"><i class="bi bi-upload"></i> CSV一括インポート</div>
    <div class="card-body">
        <?php if ($importRows): ?>
        <?php
        $insertCount = count(array_filter($importRows, fn($row) => $row['mode'] === 'insert'));
        $updateCount = count($importRows) - $insertCount;
        ?>
        <p>取り込み内容を確認してください。
            <span class="badge bg-primary">追加 <?= $insertCount ?>件</span>
            <span class="badge bg-warning text-dark">更新 <?= $updateCount ?>件</span>
            <span class="badge bg-danger">エラー <?= count($importErrors) ?>件</span>
        </p>
        <div class="table-responsive mb-3">
            <table class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>行</th><th>区分</th><th>コード</th><th>顧客名</th><th>郵便番号</th><th>住所</th><th>電話番号</th><th>会計用コード</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($importRows as $row): ?>
                    <tr>
                        <td><?= $row['line'] ?></td>
                        <td><span class="badge bg-<?= $row['mode'] === 'insert' ? 'primary' : 'warning text-dark' ?>"><?= $row['mode'] === 'insert' ? '追加' : '更新' ?></span></td>
                        <td><?= h($row['code']) ?></td>
                        <td><?= h($row['name']) ?></td>
                        <td><?= h($row['postal_code']) ?></td>
                        <td><?= h($row['address']) ?></td>
                        <td><?= h($row['tel']) ?></td>
                        <td><?= h($row['accounting_code']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="import_commit">
            <input type="hidden" name="rows" value="<?= h(json_encode($importRows, JSON_UNESCAPED_UNICODE)) ?>">
            <input type="hidden" name="errors" value="<?= h(json_encode($importErrors, JSON_UNESCAPED_UNICODE)) ?>">
            <button type="submit" class="btn btn-primary">この内容で取り込む</button>
            <a href="customers.php" class="btn btn-secondary">キャンセル</a>
        </form>
        <?php else: ?>
        <form method="post" enctype="multipart/form-data" class="row g-3 align-items-end">
            <input type="hidden" name="action" value="import_preview">
            <div class="col-md-6">
                <label class="form-label">CSVファイル</label>
                <input type="file" name="csv_file" class="form-control" accept=".csv,text/csv" required>
            </div>
            <div class="col-md-6">
                <button type="submit" class="btn btn-outline-primary">内容を確認</button>
            </div>
        </form>
        <p class="text-muted small mt-2 mb-0">列順: <?= implode(', ', IMPORT_COLUMNS) ?>（見出し行は任意、文字コードはUTF-8またはSJIS）。顧客コードをキーに、既存は更新・未登録は新規登録します。</p>
        <?php endif; ?>

        <?php if ($importErrors): ?>
        <div class="alert alert-warning mt-3 mb-0">
            <div class="fw-bold mb-2">エラー行（<?= count($importErrors) ?>件スキップ<?= $importResult ? 'しました' : 'されます' ?>）</div>
            <ul class="mb-0">
                <?php foreach ($importErrors as $importError): ?>
                <li><?= $importError['line'] ?>行目: <?= h($importError['reason']) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <?= $editCustomer ? '顧客編集' : '顧客登録' ?>
    </div>
    <div class="card-body">
        <div class="border rounded p-3 mb-3 bg-light">
            <label class="form-label mb-1"><i class="bi bi-camera"></i> 名刺から読み取り</label>
            <div class="d-flex flex-wrap align-items-center gap-2">
                <input type="file" id="cardImage" class="form-control" style="max-width: 420px" accept="image/*" capture="environment">
                <button type="button" id="cardOcrButton" class="btn btn-outline-primary">読み取る</button>
                <span id="cardOcrStatus" class="text-muted small"></span>
            </div>
            <div class="form-text">名刺を撮影または選択すると、会社名・郵便番号・住所・電話番号を下のフォームに自動入力します。内容を確認・修正してから登録してください（顧客コードは自動入力されません）。</div>
        </div>

        <form method="post">
            <input type="hidden" name="action" value="<?= $editCustomer ? 'update' : 'create' ?>">
            <?php if ($editCustomer): ?>
            <input type="hidden" name="id" value="<?= $editCustomer['id'] ?>">
            <?php endif; ?>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">顧客コード <span class="text-danger">*</span></label>
                    <input type="text" name="code" class="form-control" required value="<?= h($editCustomer['code'] ?? '') ?>">
                </div>
                <div class="col-md-5 mb-3">
                    <label class="form-label">顧客名 <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required value="<?= h($editCustomer['name'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">会計用コード</label>
                    <input type="text" name="accounting_code" class="form-control" value="<?= h($editCustomer['accounting_code'] ?? '') ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-2 mb-3">
                    <label class="form-label">郵便番号</label>
                    <input type="text" name="postal_code" class="form-control" value="<?= h($editCustomer['postal_code'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">住所</label>
                    <input type="text" name="address" class="form-control" value="<?= h($editCustomer['address'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">電話番号</label>
                    <input type="text" name="tel" class="form-control" value="<?= h($editCustomer['tel'] ?? '') ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><?= $editCustomer ? '更新' : '登録' ?></button>
            <?php if ($editCustomer): ?>
            <a href="customers.php" class="btn btn-secondary">キャンセル</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">顧客一覧</div>
    <div class="card-body">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>コード</th>
                    <th>顧客名</th>
                    <th>郵便番号</th>
                    <th>住所</th>
                    <th>電話番号</th>
                    <th>会計用コード</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($customers as $customer): ?>
                <tr>
                    <td><?= h($customer['code']) ?></td>
                    <td><?= h($customer['name']) ?></td>
                    <td><?= h($customer['postal_code']) ?></td>
                    <td><?= h($customer['address']) ?></td>
                    <td><?= h($customer['tel']) ?></td>
                    <td><?= h($customer['accounting_code']) ?></td>
                    <td>
                        <a href="?edit=<?= $customer['id'] ?>" class="btn btn-sm btn-outline-primary btn-action">編集</a>
                        <form method="post" style="display:inline" onsubmit="return confirm('削除しますか？')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $customer['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger btn-action">削除</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($customers)): ?>
                <tr><td colspan="7" class="text-center text-muted">データがありません</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
    // 名刺画像をOCRエンドポイントへ送り、登録フォームに自動入力する（登録はユーザーの確認後）
    const cardImage = document.getElementById('cardImage');
    const cardOcrButton = document.getElementById('cardOcrButton');
    const cardOcrStatus = document.getElementById('cardOcrStatus');

    cardOcrButton.addEventListener('click', async () => {
        if (!cardImage.files.length) {
            cardOcrStatus.className = 'text-danger small';
            cardOcrStatus.textContent = '名刺画像を選択してください';
            return;
        }

        const formData = new FormData();
        formData.append('card_image', cardImage.files[0]);

        cardOcrButton.disabled = true;
        cardOcrStatus.className = 'text-muted small';
        cardOcrStatus.textContent = '読み取り中...';

        try {
            const response = await fetch('business_card_ocr.php', { method: 'POST', body: formData });
            const result = await response.json();
            if (result.error) {
                cardOcrStatus.className = 'text-danger small';
                cardOcrStatus.textContent = result.error;
                return;
            }

            ['name', 'postal_code', 'address', 'tel'].forEach(field => {
                if (result[field]) {
                    document.querySelector(`[name="${field}"]`).value = result[field];
                }
            });
            cardOcrStatus.className = 'text-success small';
            cardOcrStatus.textContent = '読み取りました。内容を確認して登録してください';
        } catch (e) {
            cardOcrStatus.className = 'text-danger small';
            cardOcrStatus.textContent = '読み取りに失敗しました: ' + e.message;
        } finally {
            cardOcrButton.disabled = false;
        }
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
