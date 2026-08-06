<?php
$pageTitle = '見積一覧';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/list_filter.php';

$pdo = getDB();

$filters = getListFilterValues();
[$filterSql, $params] = buildListFilterSql(
    $filters,
    ['e.estimate_no', 'e.subject', 'c.name'],
    'e.estimate_date',
    'e.status'
);

$sql = "SELECT e.*, c.name as customer_name FROM estimates e JOIN customers c ON e.customer_id = c.id WHERE 1=1"
    . $filterSql
    . " ORDER BY e.estimate_date DESC, e.id DESC";

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

<?php renderListFilterForm($filters, $statusLabels, '見積番号・件名・顧客名で検索', '見積日'); ?>

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
                                                <a href="<?= BASE_PATH ?>/reports/estimate.php?id=<?= $estimate['id'] ?>" class="btn btn-sm btn-outline-secondary btn-action" target="_blank">印刷</a>
                                                <?php if ($estimate['status'] !== 'accepted'): ?>
                                                <a href="<?= BASE_PATH ?>/pages/orders/edit.php?from_estimate=<?= $estimate['id'] ?>" class="btn btn-sm btn-outline-success btn-action">受注変換</a>
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
