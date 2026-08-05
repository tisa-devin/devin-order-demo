<?php
$pageTitle = '顧客マスタ';
require_once __DIR__ . '/../../includes/header.php';

$pdo = getDB();
$message = '';
$error = '';

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
