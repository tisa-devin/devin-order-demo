<?php
$pageTitle = '顧客マスタ';
require_once __DIR__ . '/../../includes/header.php';

$pdo = getDB();
$message = '';
$error = '';
$importPreview = null;

const CSV_COLUMNS = ['code', 'name', 'postal_code', 'address', 'tel', 'accounting_code'];

/**
 * CSVを解析し、行ごとに登録/更新/エラーの判定結果を返す
 */
function parseCustomerCsv(string $content, PDO $pdo): array {
    if (!mb_check_encoding($content, 'UTF-8')) {
        $content = mb_convert_encoding($content, 'UTF-8', 'SJIS-win');
    }
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

    $handle = fopen('php://temp', 'r+');
    fwrite($handle, $content);
    rewind($handle);

    $existingCodes = $pdo->query("SELECT code FROM customers")->fetchAll(PDO::FETCH_COLUMN);
    $existingCodes = array_flip($existingCodes);

    $rows = [];
    $errors = [];
    $seenCodes = [];
    $lineNo = 0;
    $headerSkipped = false;

    while (($fields = fgetcsv($handle, null, ',', '"', '')) !== false) {
        $lineNo++;
        if ($fields === [null] || (count($fields) === 1 && trim((string)$fields[0]) === '')) {
            continue;
        }
        if (!$headerSkipped) {
            $headerSkipped = true;
            if (strtolower(trim((string)$fields[0])) === 'code') {
                continue;
            }
        }

        if (count($fields) < count(CSV_COLUMNS)) {
            $errors[] = ['line' => $lineNo, 'reason' => sprintf('列数が不足しています（%d列必要、%d列）', count(CSV_COLUMNS), count($fields))];
            continue;
        }

        $values = [];
        foreach (CSV_COLUMNS as $i => $column) {
            $values[$column] = trim((string)($fields[$i] ?? ''));
        }

        if ($values['code'] === '' || $values['name'] === '') {
            $errors[] = ['line' => $lineNo, 'reason' => '顧客コードと顧客名は必須です'];
            continue;
        }
        if (isset($seenCodes[$values['code']])) {
            $errors[] = ['line' => $lineNo, 'reason' => 'ファイル内で顧客コードが重複しています（' . $values['code'] . '）'];
            continue;
        }
        $seenCodes[$values['code']] = true;

        $values['line'] = $lineNo;
        $values['mode'] = isset($existingCodes[$values['code']]) ? 'update' : 'insert';
        $rows[] = $values;
    }
    fclose($handle);

    return ['rows' => $rows, 'errors' => $errors];
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
    } elseif ($action === 'csv_preview') {
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $error = 'CSVファイルを選択してください';
        } else {
            $content = file_get_contents($_FILES['csv_file']['tmp_name']);
            $parsed = parseCustomerCsv($content, $pdo);
            if (empty($parsed['rows']) && empty($parsed['errors'])) {
                $error = '取り込み対象のデータがありません';
            } else {
                $importPreview = $parsed;
            }
        }
    } elseif ($action === 'csv_import') {
        $rows = json_decode($_POST['import_rows'] ?? '[]', true);
        if (!is_array($rows) || empty($rows)) {
            $error = '取り込み対象のデータがありません';
        } else {
            $inserted = 0;
            $updated = 0;
            try {
                $pdo->beginTransaction();
                $insert = $pdo->prepare("INSERT INTO customers (code, name, postal_code, address, tel, accounting_code) VALUES (?, ?, ?, ?, ?, ?)");
                $update = $pdo->prepare("UPDATE customers SET name = ?, postal_code = ?, address = ?, tel = ?, accounting_code = ?, updated_at = CURRENT_TIMESTAMP WHERE code = ?");
                foreach ($rows as $row) {
                    if ($row['mode'] === 'update') {
                        $update->execute([$row['name'], $row['postal_code'], $row['address'], $row['tel'], $row['accounting_code'], $row['code']]);
                        $updated++;
                    } else {
                        $insert->execute([$row['code'], $row['name'], $row['postal_code'], $row['address'], $row['tel'], $row['accounting_code']]);
                        $inserted++;
                    }
                }
                $pdo->commit();
                $message = sprintf('CSVを取り込みました（追加%d件／更新%d件）', $inserted, $updated);
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = '取り込みに失敗しました: ' . $e->getMessage();
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
        <?php if ($importPreview === null): ?>
        <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
            <input type="hidden" name="action" value="csv_preview">
            <div class="col-md-6">
                <label class="form-label">CSVファイル</label>
                <input type="file" name="csv_file" accept=".csv,text/csv" class="form-control" required>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary">プレビュー</button>
            </div>
        </form>
        <p class="text-muted small mt-2 mb-0">
            列順: <code>code,name,postal_code,address,tel,accounting_code</code>（1行目が <code>code</code> で始まる場合はヘッダー行として無視。文字コードは UTF-8 / Shift_JIS 対応）<br>
            <code>code</code> をキーに、既存なら更新・なければ新規登録します。
        </p>
        <?php else: ?>
        <?php
            $insertCount = count(array_filter($importPreview['rows'], fn($r) => $r['mode'] === 'insert'));
            $updateCount = count(array_filter($importPreview['rows'], fn($r) => $r['mode'] === 'update'));
        ?>
        <p>
            <span class="badge bg-success">追加 <?= $insertCount ?>件</span>
            <span class="badge bg-primary">更新 <?= $updateCount ?>件</span>
            <span class="badge bg-danger">エラー <?= count($importPreview['errors']) ?>件</span>
        </p>
        <?php if (!empty($importPreview['rows'])): ?>
        <div class="table-responsive mb-3">
            <table class="table table-sm table-striped">
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
                    <?php foreach ($importPreview['rows'] as $row): ?>
                    <tr>
                        <td><?= $row['line'] ?></td>
                        <td>
                            <span class="badge bg-<?= $row['mode'] === 'insert' ? 'success' : 'primary' ?>"><?= $row['mode'] === 'insert' ? '追加' : '更新' ?></span>
                        </td>
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
        <?php if (!empty($importPreview['errors'])): ?>
        <div class="alert alert-warning">
            <strong>エラー行（スキップされます）</strong>
            <ul class="mb-0">
                <?php foreach ($importPreview['errors'] as $err): ?>
                <li><?= $err['line'] ?>行目: <?= h($err['reason']) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        <form method="post" class="d-inline">
            <input type="hidden" name="action" value="csv_import">
            <input type="hidden" name="import_rows" value="<?= h(json_encode($importPreview['rows'], JSON_UNESCAPED_UNICODE)) ?>">
            <button type="submit" class="btn btn-primary" <?= empty($importPreview['rows']) ? 'disabled' : '' ?>>この内容で取り込む</button>
        </form>
        <a href="customers.php" class="btn btn-secondary">キャンセル</a>
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
