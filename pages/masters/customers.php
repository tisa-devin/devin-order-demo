<?php
$pageTitle = '顧客マスタ';
require_once __DIR__ . '/../../includes/header.php';

$pdo = getDB();
$message = '';
$error = '';

$importPreview = null;    // プレビュー行の配列
$importCsvData = '';      // 確定ステップへ引き継ぐUTF-8のCSV原文
$importSummary = null;    // ['add'=>, 'update'=>, 'error'=>]
$importResultErrors = []; // 確定時にスキップしたエラー行

$CUSTOMER_CSV_COLUMNS = ['code', 'name', 'postal_code', 'address', 'tel', 'accounting_code'];

function csvToUtf8(string $content): string {
    $enc = mb_detect_encoding($content, ['UTF-8', 'SJIS-win', 'SJIS', 'EUC-JP', 'ASCII'], true);
    if ($enc && strtoupper($enc) !== 'UTF-8') {
        $content = mb_convert_encoding($content, 'UTF-8', $enc);
    }
    return preg_replace('/^\xEF\xBB\xBF/', '', $content);
}

function parseCsvRecords(string $content): array {
    $rows = [];
    $fp = fopen('php://temp', 'r+');
    fwrite($fp, $content);
    rewind($fp);
    while (($data = fgetcsv($fp, null, ',', '"', '')) !== false) {
        if (count($data) === 1 && trim((string)($data[0] ?? '')) === '') {
            continue; // 空行はスキップ
        }
        $rows[] = $data;
    }
    fclose($fp);
    return $rows;
}

/**
 * CSVをプレビュー用の行配列に変換する。
 * 各行: line, code, name, postal_code, address, tel, accounting_code, status(add|update|error), error
 */
function buildCustomerImportRows(PDO $pdo, string $content): array {
    $records = parseCsvRecords($content);
    if (empty($records)) {
        return [];
    }

    $existing = [];
    foreach ($pdo->query("SELECT code FROM customers")->fetchAll(PDO::FETCH_COLUMN) as $c) {
        $existing[$c] = true;
    }

    $result = [];
    $seenInFile = [];
    foreach ($records as $index => $data) {
        $lineNo = $index + 1;
        // 1行目がヘッダー（先頭セルが code / 顧客コード）ならスキップ
        if ($index === 0) {
            $first = strtolower(trim((string)($data[0] ?? '')));
            if ($first === 'code' || $first === '顧客コード') {
                continue;
            }
        }

        $code = trim((string)($data[0] ?? ''));
        $name = trim((string)($data[1] ?? ''));
        $row = [
            'line' => $lineNo,
            'code' => $code,
            'name' => $name,
            'postal_code' => trim((string)($data[2] ?? '')),
            'address' => trim((string)($data[3] ?? '')),
            'tel' => trim((string)($data[4] ?? '')),
            'accounting_code' => trim((string)($data[5] ?? '')),
            'status' => 'add',
            'error' => '',
        ];

        if ($code === '' || $name === '') {
            $row['status'] = 'error';
            $row['error'] = '顧客コードと顧客名は必須です';
        } elseif (isset($seenInFile[$code])) {
            $row['status'] = 'error';
            $row['error'] = 'ファイル内で顧客コードが重複しています';
        } else {
            $row['status'] = isset($existing[$code]) ? 'update' : 'add';
            $seenInFile[$code] = true;
        }

        $result[] = $row;
    }
    return $result;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'import_preview') {
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $error = 'CSVファイルをアップロードしてください';
        } else {
            $content = csvToUtf8((string)file_get_contents($_FILES['csv_file']['tmp_name']));
            $rows = buildCustomerImportRows($pdo, $content);
            if (empty($rows)) {
                $error = '取り込めるデータがありません';
            } else {
                $importPreview = $rows;
                $importCsvData = $content;
                $importSummary = ['add' => 0, 'update' => 0, 'error' => 0];
                foreach ($rows as $r) {
                    $importSummary[$r['status']]++;
                }
            }
        }
    } elseif ($action === 'import_commit') {
        $content = (string)($_POST['csv_data'] ?? '');
        $rows = buildCustomerImportRows($pdo, $content);
        $added = 0;
        $updated = 0;
        $insert = $pdo->prepare("INSERT INTO customers (code, name, postal_code, address, tel, accounting_code) VALUES (?, ?, ?, ?, ?, ?)");
        $update = $pdo->prepare("UPDATE customers SET name = ?, postal_code = ?, address = ?, tel = ?, accounting_code = ?, updated_at = CURRENT_TIMESTAMP WHERE code = ?");
        foreach ($rows as $r) {
            if ($r['status'] === 'error') {
                $importResultErrors[] = ['line' => $r['line'], 'reason' => $r['error']];
                continue;
            }
            try {
                if ($r['status'] === 'update') {
                    $update->execute([$r['name'], $r['postal_code'], $r['address'], $r['tel'], $r['accounting_code'], $r['code']]);
                    $updated++;
                } else {
                    $insert->execute([$r['code'], $r['name'], $r['postal_code'], $r['address'], $r['tel'], $r['accounting_code']]);
                    $added++;
                }
            } catch (PDOException $e) {
                $importResultErrors[] = ['line' => $r['line'], 'reason' => '登録エラー: ' . $e->getMessage()];
            }
        }
        $message = "CSV取り込みが完了しました（追加 {$added} 件 / 更新 {$updated} 件 / エラー " . count($importResultErrors) . " 件）";
    }
    
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

<?php if (!empty($importResultErrors)): ?>
<div class="card mb-4 border-warning">
    <div class="card-header bg-warning-subtle">
        <i class="bi bi-exclamation-triangle"></i> 取り込みエラー（<?= count($importResultErrors) ?> 件・スキップ）
    </div>
    <div class="card-body">
        <table class="table table-sm mb-0">
            <thead><tr><th style="width:120px;">行番号</th><th>理由</th></tr></thead>
            <tbody>
                <?php foreach ($importResultErrors as $e): ?>
                <tr><td><?= (int)$e['line'] ?> 行目</td><td><?= h($e['reason']) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">
        <i class="bi bi-upload"></i> CSV一括インポート
    </div>
    <div class="card-body">
        <?php if ($importPreview === null): ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="import_preview">
            <div class="row align-items-end">
                <div class="col-md-8 mb-2">
                    <label class="form-label">CSVファイル</label>
                    <input type="file" name="csv_file" class="form-control" accept=".csv,text/csv" required>
                </div>
                <div class="col-md-4 mb-2">
                    <button type="submit" class="btn btn-primary">プレビュー</button>
                </div>
            </div>
            <p class="text-muted small mb-0">
                列の順序: <code>code, name, postal_code, address, tel, accounting_code</code>（1行目のヘッダー行は任意）。
                <code>code</code> をキーに既存なら更新・なければ新規登録します。文字コードは UTF-8 / Shift_JIS に対応。
            </p>
        </form>
        <?php else: ?>
        <div class="alert alert-info">
            プレビュー: 追加 <strong><?= $importSummary['add'] ?></strong> 件 /
            更新 <strong><?= $importSummary['update'] ?></strong> 件 /
            エラー <strong><?= $importSummary['error'] ?></strong> 件
            <?php if ($importSummary['error'] > 0): ?>
            <span class="text-muted">（エラー行はスキップして取り込みます）</span>
            <?php endif; ?>
        </div>
        <div class="table-responsive" style="max-height:400px;overflow:auto;">
            <table class="table table-sm table-striped">
                <thead>
                    <tr>
                        <th>行</th><th>区分</th><th>コード</th><th>顧客名</th>
                        <th>郵便番号</th><th>住所</th><th>電話番号</th><th>会計用コード</th><th>備考</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($importPreview as $r): ?>
                    <?php
                        $badge = ['add' => 'bg-success', 'update' => 'bg-primary', 'error' => 'bg-danger'][$r['status']];
                        $label = ['add' => '追加', 'update' => '更新', 'error' => 'エラー'][$r['status']];
                    ?>
                    <tr class="<?= $r['status'] === 'error' ? 'table-danger' : '' ?>">
                        <td><?= (int)$r['line'] ?></td>
                        <td><span class="badge <?= $badge ?>"><?= $label ?></span></td>
                        <td><?= h($r['code']) ?></td>
                        <td><?= h($r['name']) ?></td>
                        <td><?= h($r['postal_code']) ?></td>
                        <td><?= h($r['address']) ?></td>
                        <td><?= h($r['tel']) ?></td>
                        <td><?= h($r['accounting_code']) ?></td>
                        <td class="text-danger small"><?= h($r['error']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <form method="post" class="mt-2">
            <input type="hidden" name="action" value="import_commit">
            <input type="hidden" name="csv_data" value="<?= h($importCsvData) ?>">
            <button type="submit" class="btn btn-primary"
                <?= ($importSummary['add'] + $importSummary['update']) === 0 ? 'disabled' : '' ?>>
                取り込みを確定
            </button>
            <a href="customers.php" class="btn btn-secondary">キャンセル</a>
        </form>
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
            <div class="row align-items-center g-2">
                <div class="col-md-8">
                    <input type="file" id="businessCardImage" class="form-control" accept="image/*" capture="environment">
                </div>
                <div class="col-md-4">
                    <button type="button" id="businessCardScanBtn" class="btn btn-outline-primary w-100">
                        <span id="businessCardScanLabel">読み取り</span>
                        <span id="businessCardSpinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
                    </button>
                </div>
            </div>
            <div id="businessCardResult" class="small mt-2"></div>
            <p class="text-muted small mb-0 mt-1">名刺画像から会社名・郵便番号・住所・電話番号を自動入力します。内容を確認・修正のうえ「登録」してください（顧客コードは自動入力されません）。</p>
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
(function () {
    var btn = document.getElementById('businessCardScanBtn');
    var input = document.getElementById('businessCardImage');
    var result = document.getElementById('businessCardResult');
    var spinner = document.getElementById('businessCardSpinner');
    var label = document.getElementById('businessCardScanLabel');
    if (!btn || !input) return;

    function setLoading(loading) {
        btn.disabled = loading;
        spinner.classList.toggle('d-none', !loading);
        label.textContent = loading ? '読み取り中...' : '読み取り';
    }
    function setField(name, value) {
        var el = document.querySelector('form [name="' + name + '"]');
        if (el && value) el.value = value;
    }

    btn.addEventListener('click', function () {
        if (!input.files || !input.files[0]) {
            result.innerHTML = '<span class="text-danger">画像を選択してください</span>';
            return;
        }
        result.textContent = '';
        setLoading(true);
        var fd = new FormData();
        fd.append('image', input.files[0]);
        fetch('<?= BASE_PATH ?>/pages/masters/business_card_ocr.php', { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (json) {
                if (!json.success) {
                    result.innerHTML = '<span class="text-danger">読み取りに失敗しました: ' + (json.error || '不明なエラー') + '</span>';
                    return;
                }
                var d = json.data || {};
                setField('name', d.name);
                setField('postal_code', d.postal_code);
                setField('address', d.address);
                setField('tel', d.tel);
                result.innerHTML = '<span class="text-success">読み取り結果を入力しました。内容を確認・修正して「登録」してください。</span>';
            })
            .catch(function (e) {
                result.innerHTML = '<span class="text-danger">通信エラー: ' + e.message + '</span>';
            })
            .finally(function () { setLoading(false); });
    });
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
