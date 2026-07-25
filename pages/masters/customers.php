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
            <div class="fw-bold mb-2"><i class="bi bi-camera"></i> 名刺から読み取り</div>
            <div class="row align-items-end">
                <div class="col-md-6 mb-2">
                    <label class="form-label">名刺画像（カメラ撮影または画像選択）</label>
                    <input type="file" id="businessCardImage" class="form-control" accept="image/*" capture="environment">
                </div>
                <div class="col-md-3 mb-2">
                    <button type="button" id="scanBusinessCardBtn" class="btn btn-outline-primary">読み取り</button>
                </div>
            </div>
            <div id="scanResult" class="mt-2"></div>
            <div class="form-text">読み取った内容は下のフォームに反映されます。顧客コードは自動入力されません。内容を確認・修正してから登録してください。</div>
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
const BUSINESS_CARD_MAX_EDGE = 1600;
const BUSINESS_CARD_JPEG_QUALITY = 0.85;

// スマホの高解像度写真をそのまま送るとサイズ上限に引っかかるため、送信前に縮小する。
async function shrinkImage(file) {
    const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
    const scale = Math.min(1, BUSINESS_CARD_MAX_EDGE / Math.max(bitmap.width, bitmap.height));
    const canvas = document.createElement('canvas');
    canvas.width = Math.round(bitmap.width * scale);
    canvas.height = Math.round(bitmap.height * scale);
    canvas.getContext('2d').drawImage(bitmap, 0, 0, canvas.width, canvas.height);
    bitmap.close();

    const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', BUSINESS_CARD_JPEG_QUALITY));
    if (!blob) {
        throw new Error('画像の変換に失敗しました');
    }
    return new File([blob], 'business_card.jpg', { type: 'image/jpeg' });
}

document.getElementById('scanBusinessCardBtn').addEventListener('click', async function () {
    const input = document.getElementById('businessCardImage');
    const result = document.getElementById('scanResult');
    const button = this;

    if (!input.files || input.files.length === 0) {
        result.innerHTML = '<div class="alert alert-warning py-2 mb-0">名刺画像を選択してください</div>';
        return;
    }

    button.disabled = true;
    result.innerHTML = '<div class="text-muted"><span class="spinner-border spinner-border-sm"></span> 画像を縮小中...</div>';

    try {
        let image;
        try {
            image = await shrinkImage(input.files[0]);
        } catch (e) {
            image = input.files[0];
        }

        const formData = new FormData();
        formData.append('image', image);

        result.innerHTML = '<div class="text-muted"><span class="spinner-border spinner-border-sm"></span> 読み取り中...</div>';
        const response = await fetch('scan_business_card.php', { method: 'POST', body: formData });
        const data = await response.json().catch(() => null);

        if (!response.ok || !data || data.error) {
            const message = (data && data.error) ? data.error : ('読み取りに失敗しました（HTTP ' + response.status + '）');
            result.innerHTML = '<div class="alert alert-danger py-2 mb-0"></div>';
            result.firstChild.textContent = message;
            return;
        }

        const form = document.getElementById('customerForm');
        const mapping = { name: data.company_name, postal_code: data.postal_code, address: data.address, tel: data.tel };
        const filled = [];
        for (const [field, value] of Object.entries(mapping)) {
            if (value) {
                form.elements[field].value = value;
                filled.push(field);
            }
        }

        result.innerHTML = filled.length > 0
            ? '<div class="alert alert-success py-2 mb-0">読み取りが完了しました。内容を確認してください。</div>'
            : '<div class="alert alert-warning py-2 mb-0">名刺から情報を抽出できませんでした</div>';
    } catch (e) {
        result.innerHTML = '<div class="alert alert-danger py-2 mb-0"></div>';
        result.firstChild.textContent = '通信エラーが発生しました: ' + e.message;
    } finally {
        button.disabled = false;
    }
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
