<?php
$pageTitle = '受注一覧';
require_once __DIR__ . '/../../includes/header.php';

$pdo = getDB();

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

$sql = "SELECT o.*, c.name as customer_name FROM orders o JOIN customers c ON o.customer_id = c.id WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (o.order_no LIKE ? OR o.subject LIKE ? OR c.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($status) {
    $sql .= " AND o.status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY o.order_date DESC, o.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$statusLabels = [
    'ordered' => ['label' => '受注', 'class' => 'primary'],
    'in_progress' => ['label' => '進行中', 'class' => 'warning'],
    'completed' => ['label' => '完了', 'class' => 'success'],
    'cancelled' => ['label' => 'キャンセル', 'class' => 'danger']
];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-clipboard-check"></i> 受注一覧</h2>
    <a href="edit.php" class="btn btn-primary"><i class="bi bi-plus"></i> 新規受注</a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="受注番号・件名・顧客名で検索" value="<?= h($search) ?>">
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
                    <th>受注番号</th>
                    <th>受注日</th>
                    <th>顧客名</th>
                    <th>件名</th>
                    <th>納期</th>
                    <th class="text-end">金額</th>
                    <th>ステータス</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                <tr>
                    <td><?= h($order['order_no']) ?></td>
                    <td><?= formatDate($order['order_date']) ?></td>
                    <td><?= h($order['customer_name']) ?></td>
                    <td><?= h($order['subject']) ?></td>
                    <td><?= formatDate($order['delivery_date']) ?></td>
                    <td class="text-end">&yen;<?= formatNumber($order['total_amount']) ?></td>
                    <td>
                        <?php $st = $statusLabels[$order['status']] ?? ['label' => $order['status'], 'class' => 'secondary']; ?>
                        <span class="badge bg-<?= $st['class'] ?> status-badge"><?= $st['label'] ?></span>
                    </td>
                    <td>
                        <a href="edit.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-primary btn-action">編集</a>
                        <a href="/pages/purchases/edit.php?order_id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-info btn-action">発注</a>
                        <?php if ($order['status'] === 'completed'): ?>
                        <a href="/pages/sales/edit.php?order_id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-success btn-action">売上</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($orders)): ?>
                <tr><td colspan="8" class="text-center text-muted">データがありません</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
