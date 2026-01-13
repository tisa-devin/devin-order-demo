<?php
$pageTitle = '勘定科目マスタ';
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
        $tax_class_code = trim($_POST['tax_class_code'] ?? '');
        
        if (empty($code) || empty($name)) {
            $error = '勘定科目コードと勘定科目名は必須です';
        } else {
            try {
                if ($action === 'create') {
                    $stmt = $pdo->prepare("INSERT INTO accounts (code, name, tax_class_code) VALUES (?, ?, ?)");
                    $stmt->execute([$code, $name, $tax_class_code]);
                    $message = '勘定科目を登録しました';
                } else {
                    $stmt = $pdo->prepare("UPDATE accounts SET code = ?, name = ?, tax_class_code = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
                    $stmt->execute([$code, $name, $tax_class_code, $id]);
                    $message = '勘定科目を更新しました';
                }
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'UNIQUE') !== false) {
                    $error = 'この勘定科目コードは既に使用されています';
                } else {
                    $error = 'エラーが発生しました: ' . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? null;
        try {
            $stmt = $pdo->prepare("DELETE FROM accounts WHERE id = ?");
            $stmt->execute([$id]);
            $message = '勘定科目を削除しました';
        } catch (PDOException $e) {
            $error = '削除できません';
        }
    }
}

$editAccount = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM accounts WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editAccount = $stmt->fetch();
}

$stmt = $pdo->query("SELECT * FROM accounts ORDER BY code");
$accounts = $stmt->fetchAll();
?>

<h2 class="mb-4"><i class="bi bi-journal-text"></i> 勘定科目マスタ</h2>

<?php if ($message): ?>
<div class="alert alert-success"><?= h($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-header">
        <?= $editAccount ? '勘定科目編集' : '勘定科目登録' ?>
    </div>
    <div class="card-body">
        <form method="post">
            <input type="hidden" name="action" value="<?= $editAccount ? 'update' : 'create' ?>">
            <?php if ($editAccount): ?>
            <input type="hidden" name="id" value="<?= $editAccount['id'] ?>">
            <?php endif; ?>
            <div class="row">
                <div class="col-md-3 mb-3">
                    <label class="form-label">勘定科目コード <span class="text-danger">*</span></label>
                    <input type="text" name="code" class="form-control" required value="<?= h($editAccount['code'] ?? '') ?>">
                </div>
                <div class="col-md-5 mb-3">
                    <label class="form-label">勘定科目名 <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" required value="<?= h($editAccount['name'] ?? '') ?>">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">税区分コード</label>
                    <input type="text" name="tax_class_code" class="form-control" value="<?= h($editAccount['tax_class_code'] ?? '') ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-primary"><?= $editAccount ? '更新' : '登録' ?></button>
            <?php if ($editAccount): ?>
            <a href="accounts.php" class="btn btn-secondary">キャンセル</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">勘定科目一覧</div>
    <div class="card-body">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>コード</th>
                    <th>勘定科目名</th>
                    <th>税区分コード</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($accounts as $account): ?>
                <tr>
                    <td><?= h($account['code']) ?></td>
                    <td><?= h($account['name']) ?></td>
                    <td><?= h($account['tax_class_code']) ?></td>
                    <td>
                        <a href="?edit=<?= $account['id'] ?>" class="btn btn-sm btn-outline-primary btn-action">編集</a>
                        <form method="post" style="display:inline" onsubmit="return confirm('削除しますか？')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $account['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger btn-action">削除</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($accounts)): ?>
                <tr><td colspan="4" class="text-center text-muted">データがありません</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
