<?php
$pageTitle = '顧客マスタ';
require_once __DIR__ . '/../../includes/header.php';

$pdo = getDB();
$message = '';
$error = '';

const IMPORT_COLUMNS = ['code', 'name', 'postal_code', 'address', 'tel', 'accounting_code'];

const IMPORT_HEADER_ALIASES = [
    'code' => 'code',
    '顧客コード' => 'code',
    'コード' => 'code',
    'name' => 'name',
    '顧客名' => 'name',
    'postal_code' => 'postal_code',
    '郵便番号' => 'postal_code',
    'address' => 'address',
    '住所' => 'address',
    'tel' => 'tel',
    '電話番号' => 'tel',
    'accounting_code' => 'accounting_code',
    '会計用コード' => 'accounting_code',
];

/**
 * アップロードされたCSVを行単位で検証し、取込可能行（insert / update）とエラー行に分ける。
 */
function parseCustomerCsv(string $content, PDO $pdo): array {
    $encoding = mb_detect_encoding($content, ['UTF-8', 'SJIS-win', 'EUC-JP'], true) ?: 'UTF-8';
    if ($encoding !== 'UTF-8') {
        $content = mb_convert_encoding($content, 'UTF-8', $encoding);
    }
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

    $handle = fopen('php://temp', 'r+');
    fwrite($handle, $content);
    rewind($handle);

    $header = fgetcsv($handle, 0, ',', '"', '');
    if ($header === false) {
        fclose($handle);
        return [[], [], 'CSVの内容が空です'];
    }

    $indexToColumn = [];
    foreach ($header as $i => $label) {
        $key = IMPORT_HEADER_ALIASES[trim($label)] ?? null;
        if ($key !== null) {
            $indexToColumn[$i] = $key;
        }
    }
    if (!in_array('code', $indexToColumn, true) || !in_array('name', $indexToColumn, true)) {
        fclose($handle);
        return [[], [], '1行目の見出しに code（顧客コード）と name（顧客名）が必要です'];
    }

    $existingCodes = $pdo->query("SELECT code FROM customers")->fetchAll(PDO::FETCH_COLUMN);
    $rows = [];
    $errors = [];
    $seenCodes = [];
    $lineNo = 1;

    while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
        $lineNo++;
        if ($values === [null] || (count($values) === 1 && trim((string)$values[0]) === '')) {
            continue;
        }

        $data = array_fill_keys(IMPORT_COLUMNS, '');
        foreach ($indexToColumn as $i => $key) {
            $data[$key] = trim((string)($values[$i] ?? ''));
        }

        $requiredCount = max(array_keys($indexToColumn)) + 1;
        if (count($values) < $requiredCount) {
            $errors[] = ['line' => $lineNo, 'reason' => "列数が不足しています（見出し{$requiredCount}列に対して" . count($values) . '列）'];
            continue;
        }
        if ($data['code'] === '' || $data['name'] === '') {
            $errors[] = ['line' => $lineNo, 'reason' => '顧客コードと顧客名は必須です'];
            continue;
        }
        if (isset($seenCodes[$data['code']])) {
            $errors[] = ['line' => $lineNo, 'reason' => "顧客コード {$data['code']} がファイル内で重複しています（{$seenCodes[$data['code']]}行目）"];
            continue;
        }

        $seenCodes[$data['code']] = $lineNo;
        $rows[] = [
            'line' => $lineNo,
            'mode' => in_array($data['code'], $existingCodes, true) ? 'update' : 'insert',
            'data' => $data,
        ];
    }

    fclose($handle);
    return [$rows, $errors, null];
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
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] !== UPLOAD_ERR_OK) {
            $error = 'CSVファイルを選択してください';
        } else {
            [$importRows, $importErrors, $fatal] = parseCustomerCsv(file_get_contents($_FILES['csv_file']['tmp_name']), $pdo);
            if ($fatal !== null) {
                $error = $fatal;
            } else {
                $importPreview = true;
            }
        }
    } elseif ($action === 'import_commit') {
        $importRows = json_decode($_POST['rows'] ?? '[]', true) ?: [];
        $inserted = 0;
        $updated = 0;
        // プレビュー時にスキップした行も確定後の一覧に残す
        $importErrors = json_decode($_POST['errors'] ?? '[]', true) ?: [];

        $insertStmt = $pdo->prepare("INSERT INTO customers (code, name, postal_code, address, tel, accounting_code) VALUES (?, ?, ?, ?, ?, ?)");
        $updateStmt = $pdo->prepare("UPDATE customers SET name = ?, postal_code = ?, address = ?, tel = ?, accounting_code = ?, updated_at = CURRENT_TIMESTAMP WHERE code = ?");

        foreach ($importRows as $row) {
            $data = $row['data'] ?? [];
            try {
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE code = ?");
                $stmt->execute([$data['code'] ?? '']);
                if ((int)$stmt->fetchColumn() > 0) {
                    $updateStmt->execute([$data['name'], $data['postal_code'], $data['address'], $data['tel'], $data['accounting_code'], $data['code']]);
                    $updated++;
                } else {
                    $insertStmt->execute([$data['code'], $data['name'], $data['postal_code'], $data['address'], $data['tel'], $data['accounting_code']]);
                    $inserted++;
                }
            } catch (PDOException $e) {
                $importErrors[] = ['line' => $row['line'] ?? '-', 'reason' => '登録に失敗しました: ' . $e->getMessage()];
            }
        }

        $message = "CSV取込を完了しました（追加 {$inserted}件 / 更新 {$updated}件 / エラー " . count($importErrors) . "件）";
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

$importRows = $importRows ?? [];
$importErrors = $importErrors ?? [];
$importPreview = $importPreview ?? false;
$importInsertCount = count(array_filter($importRows, fn($r) => ($r['mode'] ?? '') === 'insert'));
$importUpdateCount = count(array_filter($importRows, fn($r) => ($r['mode'] ?? '') === 'update'));

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
        <?= $editCustomer ? '顧客編集' : '顧客登録' ?>
    </div>
    <div class="card-body">
        <div class="border rounded p-3 mb-3 bg-body-tertiary">
            <label class="form-label mb-1"><i class="bi bi-camera"></i> 名刺から読み取り</label>
            <div class="row g-2 align-items-center">
                <div class="col-md-6">
                    <input type="file" id="cardImage" accept="image/*" capture="environment" class="form-control">
                </div>
                <div class="col-md-6">
                    <button type="button" id="cardScanButton" class="btn btn-outline-primary">
                        <span id="cardScanSpinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
                        読み取って自動入力
                    </button>
                    <button type="button" id="cameraStartButton" class="btn btn-outline-secondary">
                        <i class="bi bi-camera-video"></i> カメラで撮影
                    </button>
                    <span class="text-muted small ms-2">顧客コードは自動入力されません。内容を確認・修正して登録してください。</span>
                </div>
            </div>
            <div id="cameraArea" class="mt-3 d-none">
                <video id="cameraVideo" class="border rounded w-100" style="max-width: 480px;" playsinline muted></video>
                <div class="mt-2">
                    <button type="button" id="cameraCaptureButton" class="btn btn-primary btn-sm">撮影して読み取る</button>
                    <button type="button" id="cameraStopButton" class="btn btn-secondary btn-sm">カメラを閉じる</button>
                </div>
            </div>
            <div id="cardScanMessage" class="mt-2 mb-0"></div>
        </div>

        <form method="post" id="customerForm">
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

<div class="card mb-4">
    <div class="card-header"><i class="bi bi-upload"></i> CSV一括インポート</div>
    <div class="card-body">
        <?php if ($importPreview): ?>
            <p class="mb-2">
                取込内容を確認してください：
                <span class="badge bg-primary">追加 <?= $importInsertCount ?>件</span>
                <span class="badge bg-success">更新 <?= $importUpdateCount ?>件</span>
                <span class="badge bg-danger">エラー <?= count($importErrors) ?>件</span>
            </p>
            <div class="table-responsive mb-3" style="max-height: 320px;">
                <table class="table table-sm table-striped">
                    <thead>
                        <tr>
                            <th>行</th>
                            <th>区分</th>
                            <th>顧客コード</th>
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
                            <td><?= h($row['line']) ?></td>
                            <td>
                                <?php if ($row['mode'] === 'insert'): ?>
                                <span class="badge bg-primary status-badge">追加</span>
                                <?php else: ?>
                                <span class="badge bg-success status-badge">更新</span>
                                <?php endif; ?>
                            </td>
                            <?php foreach (IMPORT_COLUMNS as $column): ?>
                            <td><?= h($row['data'][$column]) ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($importRows)): ?>
                        <tr><td colspan="8" class="text-center text-muted">取込可能な行がありません</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <form method="post">
                <input type="hidden" name="action" value="import_commit">
                <input type="hidden" name="rows" value="<?= h(json_encode($importRows, JSON_UNESCAPED_UNICODE)) ?>">
                <input type="hidden" name="errors" value="<?= h(json_encode($importErrors, JSON_UNESCAPED_UNICODE)) ?>">
                <button type="submit" class="btn btn-primary" <?= empty($importRows) ? 'disabled' : '' ?>>この内容で取込を確定する</button>
                <a href="customers.php" class="btn btn-secondary">キャンセル</a>
            </form>
        <?php else: ?>
            <form method="post" enctype="multipart/form-data" class="row g-3 align-items-end">
                <input type="hidden" name="action" value="import_preview">
                <div class="col-md-6">
                    <label class="form-label">CSVファイル</label>
                    <input type="file" name="csv_file" accept=".csv,text/csv" class="form-control" required>
                    <div class="form-text">
                        1行目は見出し行。<code>code</code>（顧客コード）と <code>name</code>（顧客名）は必須で、
                        <code>postal_code</code> / <code>address</code> / <code>tel</code> / <code>accounting_code</code> は任意。
                        日本語の見出し（顧客コード・顧客名など）も認識します。<code>code</code> が既存なら更新、なければ新規登録します。
                    </div>
                </div>
                <div class="col-md-6">
                    <button type="submit" class="btn btn-outline-primary">プレビュー</button>
                </div>
            </form>
        <?php endif; ?>

        <?php if (!empty($importErrors)): ?>
        <div class="alert alert-danger mt-3 mb-0">
            <strong>エラー行（スキップ）: <?= count($importErrors) ?>件</strong>
            <ul class="mb-0">
                <?php foreach ($importErrors as $importError): ?>
                <li><?= h($importError['line']) ?>行目: <?= h($importError['reason']) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
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
    var input = document.getElementById('cardImage');
    var button = document.getElementById('cardScanButton');
    var spinner = document.getElementById('cardScanSpinner');
    var messageBox = document.getElementById('cardScanMessage');
    var form = document.getElementById('customerForm');
    var cameraArea = document.getElementById('cameraArea');
    var video = document.getElementById('cameraVideo');
    var cameraStartButton = document.getElementById('cameraStartButton');
    var cameraStopButton = document.getElementById('cameraStopButton');
    var cameraCaptureButton = document.getElementById('cameraCaptureButton');
    var stream = null;

    function showMessage(text, type) {
        messageBox.innerHTML = '<div class="alert alert-' + type + ' py-2 mb-0">' + text + '</div>';
    }

    function scan(blob) {
        var body = new FormData();
        body.append('image', blob, blob.name || 'card.jpg');

        button.disabled = true;
        cameraCaptureButton.disabled = true;
        spinner.classList.remove('d-none');
        showMessage('読み取り中です...', 'info');

        // Basic認証付きURL（user:pass@host）で開かれている場合、相対URLのまま fetch すると
        // 資格情報を含むURLとして解決され Chrome に拒否されるため除去する
        var endpoint = new URL('business_card_ocr.php', location.href);
        endpoint.username = '';
        endpoint.password = '';

        fetch(endpoint.toString(), { method: 'POST', body: body })
            .then(function (res) { return res.json().then(function (json) { return { ok: res.ok, json: json }; }); })
            .then(function (result) {
                if (!result.ok) {
                    showMessage(result.json.error || '読み取りに失敗しました。', 'danger');
                    return;
                }
                var filled = [];
                ['name', 'postal_code', 'address', 'tel'].forEach(function (field) {
                    var value = result.json[field] || '';
                    if (value !== '') {
                        form.elements[field].value = value;
                        filled.push(field);
                    }
                });
                if (filled.length === 0) {
                    showMessage('項目を読み取れませんでした。画像を変えて再度お試しください。', 'warning');
                } else {
                    showMessage('読み取りました（' + filled.length + '項目）。内容を確認し、顧客コードを入力して登録してください。', 'success');
                }
            })
            .catch(function (e) {
                showMessage('通信エラー: ' + e.message, 'danger');
            })
            .finally(function () {
                button.disabled = false;
                cameraCaptureButton.disabled = false;
                spinner.classList.add('d-none');
            });
    }

    function stopCamera() {
        if (stream) {
            stream.getTracks().forEach(function (track) { track.stop(); });
            stream = null;
        }
        video.srcObject = null;
        cameraArea.classList.add('d-none');
    }

    button.addEventListener('click', function () {
        if (!input.files || input.files.length === 0) {
            showMessage('名刺画像を選択または撮影してください。', 'warning');
            return;
        }
        scan(input.files[0]);
    });

    cameraStartButton.addEventListener('click', function () {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            showMessage('このブラウザはカメラ撮影に対応していません。', 'danger');
            return;
        }
        navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment', width: { ideal: 1920 } } })
            .then(function (mediaStream) {
                stream = mediaStream;
                video.srcObject = mediaStream;
                cameraArea.classList.remove('d-none');
                showMessage('名刺が枠内に収まるように置き、「撮影して読み取る」を押してください。', 'info');
                return video.play();
            })
            .catch(function (e) {
                // HTTPSでない場合やカメラ権限が拒否された場合はここに来る
                showMessage('カメラを起動できませんでした（' + e.name + '）。ブラウザのカメラ権限とHTTPS接続を確認してください。', 'danger');
            });
    });

    cameraStopButton.addEventListener('click', stopCamera);

    cameraCaptureButton.addEventListener('click', function () {
        if (!stream) {
            showMessage('先に「カメラで撮影」を押してカメラを起動してください。', 'warning');
            return;
        }
        var canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
        canvas.toBlob(function (blob) {
            if (!blob) {
                showMessage('撮影に失敗しました。もう一度お試しください。', 'danger');
                return;
            }
            stopCamera();
            scan(blob);
        }, 'image/jpeg', 0.92);
    });

    window.addEventListener('beforeunload', stopCamera);
})();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
