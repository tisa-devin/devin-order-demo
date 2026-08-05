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
        $contact_name = trim($_POST['contact_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $job_title = trim($_POST['job_title'] ?? '');
        $accounting_code = trim($_POST['accounting_code'] ?? '');
        
        if (empty($code) || empty($name)) {
            $error = '顧客コードと顧客名は必須です';
        } else {
            try {
                if ($action === 'create') {
                    $stmt = $pdo->prepare("INSERT INTO customers (code, name, postal_code, address, tel, contact_name, email, department, job_title, accounting_code) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$code, $name, $postal_code, $address, $tel, $contact_name, $email, $department, $job_title, $accounting_code]);
                    $message = '顧客を登録しました';
                } else {
                    $stmt = $pdo->prepare("UPDATE customers SET code = ?, name = ?, postal_code = ?, address = ?, tel = ?, contact_name = ?, email = ?, department = ?, job_title = ?, accounting_code = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$code, $name, $postal_code, $address, $tel, $contact_name, $email, $department, $job_title, $accounting_code, $id]);
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
        <?php if (!$editCustomer): ?>
        <div class="border rounded p-3 mb-3 bg-light">
            <div class="row align-items-end">
                <div class="col-md-6 mb-2">
                    <label class="form-label"><i class="bi bi-camera"></i> 名刺画像</label>
                    <input type="file" id="cardImage" class="form-control" accept="image/*" capture="environment">
                </div>
                <div class="col-md-6 mb-2">
                    <button type="button" id="cardReadBtn" class="btn btn-outline-primary">
                        <span id="cardSpinner" class="spinner-border spinner-border-sm d-none" role="status"></span>
                        名刺から読み取り
                    </button>
                    <span class="text-muted ms-2">※抽出後に内容を確認・修正して登録してください（顧客コードは自動入力されません）</span>
                </div>
            </div>
            <div id="cardMessage" class="mt-2"></div>
        </div>
        <?php endif; ?>
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
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">担当者名</label>
                    <input type="text" name="contact_name" class="form-control" value="<?= h($editCustomer['contact_name'] ?? '') ?>">
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">部署名</label>
                    <input type="text" name="department" class="form-control" value="<?= h($editCustomer['department'] ?? '') ?>">
                </div>
                <div class="col-md-2 mb-3">
                    <label class="form-label">役職</label>
                    <input type="text" name="job_title" class="form-control" value="<?= h($editCustomer['job_title'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">メールアドレス</label>
                    <input type="email" name="email" class="form-control" value="<?= h($editCustomer['email'] ?? '') ?>">
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
                    <th>担当者名</th>
                    <th>部署名</th>
                    <th>役職</th>
                    <th>メールアドレス</th>
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
                    <td><?= h($customer['contact_name'] ?? '') ?></td>
                    <td><?= h($customer['department'] ?? '') ?></td>
                    <td><?= h($customer['job_title'] ?? '') ?></td>
                    <td><?= h($customer['email'] ?? '') ?></td>
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
                <tr><td colspan="11" class="text-center text-muted">データがありません</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if (!$editCustomer): ?>
<script>
(function () {
    const input = document.getElementById('cardImage');
    const btn = document.getElementById('cardReadBtn');
    const spinner = document.getElementById('cardSpinner');
    const message = document.getElementById('cardMessage');
    if (!input || !btn) return;

    function showMessage(text, type) {
        message.innerHTML = text ? '<div class="alert alert-' + type + ' py-2 mb-0">' + text + '</div>' : '';
    }

    btn.addEventListener('click', async function () {
        const file = input.files && input.files[0];
        if (!file) {
            showMessage('名刺画像を選択してください', 'warning');
            return;
        }
        showMessage('', '');
        btn.disabled = true;
        spinner.classList.remove('d-none');
        try {
            const formData = new FormData();
            formData.append('card_image', file);
            // ページURLに認証情報が含まれる場合、相対URLのままだとfetchが拒否されるため除去する
            const endpoint = new URL('<?= BASE_PATH ?>/pages/masters/extract_business_card.php', location.href);
            endpoint.username = '';
            endpoint.password = '';
            const res = await fetch(endpoint.toString(), {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            if (!res.ok) {
                showMessage(data.error || '読み取りに失敗しました', 'danger');
                return;
            }
            const form = document.querySelector('form[method="post"]');
            const map = {
                name: data.company_name,
                postal_code: data.postal_code,
                address: data.address,
                tel: data.tel,
                contact_name: data.contact_name,
                email: data.email,
                department: data.department,
                job_title: data.job_title
            };
            Object.keys(map).forEach(function (key) {
                const el = form.querySelector('[name="' + key + '"]');
                if (el && map[key]) el.value = map[key];
            });
            showMessage('読み取りが完了しました。内容を確認・修正して登録してください。', 'success');
        } catch (e) {
            showMessage('読み取り中にエラーが発生しました: ' + e.message, 'danger');
        } finally {
            btn.disabled = false;
            spinner.classList.add('d-none');
        }
    });
})();
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
