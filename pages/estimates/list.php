<?php
$pageTitle = '見積一覧';
require_once __DIR__ . '/../../includes/header.php';

$pdo = getDB();

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

$sql = "SELECT e.*, c.name as customer_name FROM estimates e JOIN customers c ON e.customer_id = c.id WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (e.estimate_no LIKE ? OR e.subject LIKE ? OR c.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($status) {
    $sql .= " AND e.status = ?";
    $params[] = $status;
}

$sql .= " ORDER BY e.estimate_date DESC, e.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$estimates = $stmt->fetchAll();

$statusLabels = [
    'draft' => ['label' => '下書き', 'class' => 'secondary'],
    'sent' => ['label' => '送付済', 'class' => 'primary'],
    'accepted' => ['label' => '受諾', 'class' => 'success'],
    'rejected' => ['label' => '却下', 'class' => 'danger']
];
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-file-earmark-text"></i> 見積一覧</h2>
    <a href="edit.php" class="btn btn-primary"><i class="bi bi-plus"></i> 新規見積</a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-4">
                <input type="text" name="search" class="form-control" placeholder="見積番号・件名・顧客名で検索" value="<?= h($search) ?>">
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
                    <th>見積番号</th>
                    <th>見積日</th>
                    <th>顧客名</th>
                    <th>件名</th>
                    <th>有効期限</th>
                    <th class="text-end">金額</th>
                    <th>ステータス</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($estimates as $estimate): ?>
                <tr>
                    <td><?= h($estimate['estimate_no']) ?></td>
                    <td><?= formatDate($estimate['estimate_date']) ?></td>
                    <td><?= h($estimate['customer_name']) ?></td>
                    <td><?= h($estimate['subject']) ?></td>
                    <td><?= formatDate($estimate['valid_until']) ?></td>
                    <td class="text-end">&yen;<?= formatNumber($estimate['total_amount']) ?></td>
                    <td>
                        <?php $st = $statusLabels[$estimate['status']] ?? ['label' => $estimate['status'], 'class' => 'secondary']; ?>
                        <span class="badge bg-<?= $st['class'] ?> status-badge"><?= $st['label'] ?></span>
                    </td>
                    <td>
                        <a href="edit.php?id=<?= $estimate['id'] ?>" class="btn btn-sm btn-outline-primary btn-action">編集</a>
                        <a href="/reports/estimate.php?id=<?= $estimate['id'] ?>" class="btn btn-sm btn-outline-secondary btn-action" target="_blank">印刷</a>
                        <?php if ($estimate['status'] !== 'accepted'): ?>
                        <a href="/pages/orders/edit.php?from_estimate=<?= $estimate['id'] ?>" class="btn btn-sm btn-outline-success btn-action">受注変換</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($estimates)): ?>
                <tr><td colspan="8" class="text-center text-muted">データがありません</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
