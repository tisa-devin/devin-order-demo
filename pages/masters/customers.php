<?php
$pageTitle = '顧客マスタ';
require_once __DIR__ . '/../../includes/header.php';

$pdo = getDB();
$message = '';
$error = '';

// CSVインポートの列（キー => 受け付けるヘッダー名）
const CSV_COLUMNS = [
    'code' => ['code', '顧客コード'],
    'name' => ['name', '顧客名'],
    'postal_code' => ['postal_code', '郵便番号'],
    'address' => ['address', '住所'],
    'tel' => ['tel', '電話番号'],
    'accounting_code' => ['accounting_code', '会計用コード', '勘定科目コード'],
];

$importRows = null;   // プレビュー対象の取り込み行
$importErrors = [];   // ['line' => 行番号, 'reason' => 理由]

/**
 * アップロードされたCSVを解析し、取り込み行とエラー行に振り分ける。
 * 戻り値は [取り込み行, エラー行, 全体エラーメッセージ]。
 */
function parseCustomerCsv(string $path, PDO $pdo): array {
    $content = (string)file_get_contents($path);
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
    if (!mb_check_encoding($content, 'UTF-8')) {
        $content = mb_convert_encoding($content, 'UTF-8', 'SJIS-win');
    }

    $tmp = fopen('php://temp', 'r+');
    fwrite($tmp, $content);
    rewind($tmp);

    $existing = $pdo->query("SELECT code, id FROM customers")->fetchAll(PDO::FETCH_KEY_PAIR);
    $columnIndex = [];
    $rows = [];
    $errors = [];
    $seenCodes = [];
    $lineNo = 0;

    while (($fields = fgetcsv($tmp, 0, ',', '"', '')) !== false) {
        $lineNo++;
        if (count($fields) === 1 && trim((string)$fields[0]) === '') {
            continue;
        }

        if (!$columnIndex) {
            foreach ($fields as $i => $header) {
                $header = trim(str_replace([' ', '　'], '', (string)$header));
                foreach (CSV_COLUMNS as $key => $aliases) {
                    if (in_array($header, $aliases, true)) {
                        $columnIndex[$key] = $i;
                    }
                }
            }
            if (!isset($columnIndex['code'], $columnIndex['name'])) {
                fclose($tmp);
                return [null, [], 'ヘッダー行に code（顧客コード）と name（顧客名）が必要です'];
            }
            continue;
        }

        $row = [];
        foreach (array_keys(CSV_COLUMNS) as $key) {
            $row[$key] = isset($columnIndex[$key]) ? trim((string)($fields[$columnIndex[$key]] ?? '')) : '';
        }

        if ($row['code'] === '' || $row['name'] === '') {
            $errors[] = ['line' => $lineNo, 'reason' => '顧客コードまたは顧客名が空です'];
            continue;
        }
        if (mb_strlen($row['code']) > 20) {
            $errors[] = ['line' => $lineNo, 'reason' => '顧客コードが長すぎます（20文字以内）: ' . $row['code']];
            continue;
        }
        if (isset($seenCodes[$row['code']])) {
            $errors[] = ['line' => $lineNo, 'reason' => 'ファイル内で顧客コードが重複しています（' . $seenCodes[$row['code']] . '行目と重複）: ' . $row['code']];
            continue;
        }

        $seenCodes[$row['code']] = $lineNo;
        $row['line'] = $lineNo;
        $row['mode'] = isset($existing[$row['code']]) ? 'update' : 'insert';
        $rows[] = $row;
    }
    fclose($tmp);

    if (!$columnIndex) {
        return [null, [], 'CSVの内容が空です'];
    }

    return [$rows, $errors, ''];
}

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
        $file = $_FILES['csv'] ?? null;
        if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $error = 'CSVファイルを選択してください';
        } else {
            [$importRows, $importErrors, $parseError] = parseCustomerCsv($file['tmp_name'], $pdo);
            if ($parseError) {
                $error = $parseError;
                $importRows = null;
            } elseif (!$importRows && !$importErrors) {
                $error = '取り込める行がありませんでした';
                $importRows = null;
            }
        }
    } elseif ($action === 'import_commit') {
        $decoded = json_decode($_POST['rows'] ?? '', true);
        if (!is_array($decoded) || !$decoded) {
            $error = '取り込み対象がありません。もう一度CSVを選択してください';
        } else {
            $inserted = 0;
            $updated = 0;
            try {
                $pdo->beginTransaction();
                $existsStmt = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE code = ?");
                $insertStmt = $pdo->prepare("INSERT INTO customers (code, name, postal_code, address, tel, accounting_code) VALUES (?, ?, ?, ?, ?, ?)");
                $updateStmt = $pdo->prepare("UPDATE customers SET name = ?, postal_code = ?, address = ?, tel = ?, accounting_code = ?, updated_at = CURRENT_TIMESTAMP WHERE code = ?");
                foreach ($decoded as $row) {
                    $values = [];
                    foreach (array_keys(CSV_COLUMNS) as $key) {
                        $values[$key] = trim((string)($row[$key] ?? ''));
                    }
                    if ($values['code'] === '' || $values['name'] === '') {
                        continue;
                    }
                    $existsStmt->execute([$values['code']]);
                    if ((int)$existsStmt->fetchColumn() > 0) {
                        $updateStmt->execute([$values['name'], $values['postal_code'], $values['address'], $values['tel'], $values['accounting_code'], $values['code']]);
                        $updated++;
                    } else {
                        $insertStmt->execute([$values['code'], $values['name'], $values['postal_code'], $values['address'], $values['tel'], $values['accounting_code']]);
                        $inserted++;
                    }
                }
                $pdo->commit();
                $message = "CSVを取り込みました（追加 {$inserted} 件 / 更新 {$updated} 件）";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = '取り込みに失敗したため、すべて元に戻しました: ' . $e->getMessage();
            }
        }
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
    <div class="card-header">
        <i class="bi bi-upload"></i> CSV一括インポート
    </div>
    <div class="card-body">
        <?php if ($importRows !== null): ?>
            <?php
            $insertCount = count(array_filter($importRows, fn($r) => $r['mode'] === 'insert'));
            $updateCount = count($importRows) - $insertCount;
            ?>
            <p class="mb-2">
                取り込み内容を確認してください：
                <span class="badge bg-success">追加 <?= $insertCount ?> 件</span>
                <span class="badge bg-primary">更新 <?= $updateCount ?> 件</span>
                <span class="badge bg-danger">エラー <?= count($importErrors) ?> 件</span>
            </p>
            <?php if ($importRows): ?>
            <div class="table-responsive mb-3" style="max-height: 320px; overflow-y: auto;">
                <table class="table table-sm table-striped mb-0">
                    <thead>
                        <tr>
                            <th>行</th>
                            <th>区分</th>
                            <th>コード</th>
                            <th>顧客名</th>
                            <th>郵便番号</th>
                            <th>住所</th>
                            <th>電話番号</th>
                            <th>会計用コード</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($importRows as $row): ?>
                        <tr>
                            <td><?= (int)$row['line'] ?></td>
                            <td><span class="badge bg-<?= $row['mode'] === 'insert' ? 'success' : 'primary' ?>"><?= $row['mode'] === 'insert' ? '追加' : '更新' ?></span></td>
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
            <?php endif; ?>

            <?php if ($importErrors): ?>
            <div class="alert alert-warning">
                <strong>エラー行（スキップされます）</strong>
                <ul class="mb-0">
                    <?php foreach ($importErrors as $err): ?>
                    <li><?= (int)$err['line'] ?> 行目: <?= h($err['reason']) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <form method="post" class="d-inline">
                <input type="hidden" name="action" value="import_commit">
                <input type="hidden" name="rows" value="<?= h(json_encode($importRows, JSON_UNESCAPED_UNICODE)) ?>">
                <button type="submit" class="btn btn-primary" <?= $importRows ? '' : 'disabled' ?>>この内容で取り込む</button>
            </form>
            <a href="customers.php" class="btn btn-secondary">キャンセル</a>
        <?php else: ?>
            <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
                <input type="hidden" name="action" value="import_preview">
                <div class="col-md-6">
                    <label class="form-label">CSVファイル</label>
                    <input type="file" name="csv" class="form-control" accept=".csv,text/csv" required>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-outline-primary">内容を確認</button>
                </div>
            </form>
            <p class="text-muted small mb-0 mt-2">
                1行目はヘッダー行（<code>code, name, postal_code, address, tel, accounting_code</code>、または 顧客コード, 顧客名, 郵便番号, 住所, 電話番号, 会計用コード）。
                <code>code</code> をキーに、既存なら更新・なければ新規登録します。文字コードは UTF-8 / Shift_JIS のどちらでも取り込めます。
            </p>
        <?php endif; ?>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header">
        <?= $editCustomer ? '顧客編集' : '顧客登録' ?>
    </div>
    <div class="card-body">
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
