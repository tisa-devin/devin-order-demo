<?php
$pageTitle = '仕入先マスタ';
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
            $error = '仕入先コードと仕入先名は必須です';
        } else {
            try {
                if ($action === 'create') {
                    $stmt = $pdo->prepare("INSERT INTO suppliers (code, name, postal_code, address, tel, accounting_code) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$code, $name, $postal_code, $address, $tel, $accounting_code]);
                    $message = '仕入先を登録しました';
                } else {
                    $stmt = $pdo->prepare("UPDATE suppliers SET code = ?, name = ?, postal_code = ?, address = ?, tel = ?, accounting_code = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$code, $name, $postal_code, $address, $tel, $accounting_code, $id]);
                    $message = '仕入先を更新しました';
                }
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'UNIQUE') !== false) {
                    $error = 'この仕入先コードは既に使用されています';
                } else {
                    $error = 'エラーが発生しました: ' . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? null;
        try {
            $stmt = $pdo->prepare("DELETE FROM suppliers WHERE id = ?");
            $stmt->execute([$id]);
            $message = '仕入先を削除しました';
        } catch (PDOException $e) {
            $error = '削除できません（関連データが存在します）';
        }
    }
}

$editSupplier = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editSupplier = $stmt->fetch();
}

$stmt = $pdo->query("SELECT * FROM suppliers ORDER BY code");
$suppliers = $stmt->fetchAll();
?>

<h2 class="mb-4"><i class="bi bi-truck"></i> 仕入先マスタ</h2>

<?php if ($message): ?>
<div class="alert alert-success"><?= h($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">
        <?= $editSupplier ? '仕入先編集' : '仕入先登録' ?>
    </div>
    <div class="card-body">
        <form method="post">
            <input type="hidden" name="action" value="<?= $editSupplier ? 'update' : 'create' ?>">
            <?php if ($editSupplier): ?>
            <input type="hidden" name="id" value="<?= $editSupplier['id'] ?>">
            <?php endif; ?>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">仕入先コード <span class="text-danger">*</span></label>
                    <input type="text" name="code" class="form-control" required value="<?= h($editSupplier['code'] ?? '') ?>">
                </div>
                <div class="col-md-5 mb-3">
                    <label class="form-label">仕入先名 <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required value="<?= h($editSupplier['name'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">会計用コード</label>
                    <input type="text" name="accounting_code" class="form-control" value="<?= h($editSupplier['accounting_code'] ?? '') ?>">
                </div>
            </div>
            <div class="row">
                <div class="col-md-2 mb-3">
                    <label class="form-label">郵便番号</label>
                    <input type="text" name="postal_code" class="form-control" value="<?= h($editSupplier['postal_code'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">住所</label>
                    <input type="text" name="address" class="form-control" value="<?= h($editSupplier['address'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">電話番号</label>
                    <input type="text" name="tel" class="form-control" value="<?= h($editSupplier['tel'] ?? '') ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><?= $editSupplier ? '更新' : '登録' ?></button>
            <?php if ($editSupplier): ?>
            <a href="suppliers.php" class="btn btn-secondary">キャンセル</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">仕入先一覧</div>
    <div class="card-body">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>コード</th>
                    <th>仕入先名</th>
                    <th>郵便番号</th>
                    <th>住所</th>
                    <th>電話番号</th>
                    <th>会計用コード</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($suppliers as $supplier): ?>
                <tr>
                    <td><?= h($supplier['code']) ?></td>
                    <td><?= h($supplier['name']) ?></td>
                    <td><?= h($supplier['postal_code']) ?></td>
                    <td><?= h($supplier['address']) ?></td>
                    <td><?= h($supplier['tel']) ?></td>
                    <td><?= h($supplier['accounting_code']) ?></td>
                    <td>
                        <a href="?edit=<?= $supplier['id'] ?>" class="btn btn-sm btn-outline-primary btn-action">編集</a>
                        <form method="post" style="display:inline" onsubmit="return confirm('削除しますか？')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $supplier['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger btn-action">削除</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($suppliers)): ?>
                <tr><td colspan="7" class="text-center text-muted">データがありません</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
