<?php
$pageTitle = '発注一覧';
require_once __DIR__ . '/../../includes/header.php';

$pdo = getDB();

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

$sql = "SELECT p.*, s.name as supplier_name, o.order_no FROM purchases p JOIN suppliers s ON p.supplier_id = s.id JOIN orders o ON p.order_id = o.id WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (p.purchase_no LIKE ? OR s.name LIKE ? OR o.order_no LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($status) {
    $sql .= " AND p.status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY p.purchase_date DESC, p.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$purchases = $stmt->fetchAll();

$statusLabels = [
    'ordered' => ['label' => '発注済', 'class' => 'primary'],
    'received' => ['label' => '検収済', 'class' => 'success'],
    'cancelled' => ['label' => 'キャンセル', 'class' => 'danger']
];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-cart"></i> 発注一覧</h2>
    <a href="edit.php" class="btn btn-primary"><i class="bi bi-plus"></i> 新規発注</a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="発注番号・仕入先名・受注番号で検索" value="<?= h($search) ?>">
            </div>
            <div class="col-md-3">
                <select name="status" class="form-select">
                    <option value="">全てのステータス</option>
                    <?php foreach ($statusLabels as $key => $val): ?>
                    <option value="<?= $key ?>" <?= $status === $key ? 'selected' : '' ?>><?= $val['label'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-outline-primary">検索</button>
                <a href="list.php" class="btn btn-outline-secondary">クリア</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>発注番号</th>
                    <th>発注日</th>
                    <th>仕入先名</th>
                    <th>受注番号</th>
                    <th>希望納期</th>
                    <th class="text-end">金額</th>
                    <th>ステータス</th>
                    <th>検収日</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($purchases as $purchase): ?>
                <tr>
                    <td><?= h($purchase['purchase_no']) ?></td>
                    <td><?= formatDate($purchase['purchase_date']) ?></td>
                    <td><?= h($purchase['supplier_name']) ?></td>
                    <td><?= h($purchase['order_no']) ?></td>
                    <td><?= formatDate($purchase['delivery_date']) ?></td>
                    <td class="text-end">&yen;<?= formatNumber($purchase['total_amount']) ?></td>
                    <td>
                        <?php $st = $statusLabels[$purchase['status']] ?? ['label' => $purchase['status'], 'class' => 'secondary']; ?>
                        <span class="badge bg-<?= $st['class'] ?> status-badge"><?= $st['label'] ?></span>
                    </td>
                    <td><?= formatDate($purchase['received_date']) ?></td>
                    <td>
                        <a href="edit.php?id=<?= $purchase['id'] ?>" class="btn btn-sm btn-outline-primary btn-action">編集</a>
                        <a href="<?= BASE_PATH ?>/reports/purchase.php?id=<?= $purchase['id'] ?>" class="btn btn-sm btn-outline-secondary btn-action" target="_blank">印刷</a>
                        <?php if ($purchase['status'] === 'ordered'): ?>
                        <form method="post" action="receive.php" style="display:inline">
                            <input type="hidden" name="id" value="<?= $purchase['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-success btn-action" onclick="return confirm('検収処理を行いますか？')">検収</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($purchases)): ?>
                <tr><td colspan="9" class="text-center text-muted">データがありません</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
