<?php
$pageTitle = '受注一覧';
require_once __DIR__ . '/../../includes/header.php';

$pdo = getDB();

$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? '';

$where = " FROM orders o JOIN customers c ON o.customer_id = c.id WHERE 1=1";
$params = [];

if ($search) {
    $where .= " AND (o.order_no LIKE ? OR o.subject LIKE ? OR c.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($status) {
    $where .= " AND o.status = ?";
    $params[] = $status;
}

$perPage = 20;
$page = max(1, (int)($_GET['page'] ?? 1));

$countStmt = $pdo->prepare("SELECT COUNT(*)" . $where);
$countStmt->execute($params);
$totalCount = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalCount / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("SELECT o.*, c.name as customer_name" . $where . " ORDER BY o.order_date DESC, o.id DESC LIMIT ? OFFSET ?");
foreach ($params as $i => $param) {
    $stmt->bindValue($i + 1, $param);
}
$stmt->bindValue(count($params) + 1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
$stmt->execute();
$orders = $stmt->fetchAll();

function orderPageUrl(int $page): string {
    $query = array_merge($_GET, ['page' => $page]);
    return 'list.php?' . http_build_query($query);
}

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
                        <?php if ($order['status'] !== 'cancelled'): ?>
                        <a href="<?= BASE_PATH ?>/pages/purchases/edit.php?order_id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-info btn-action">発注</a>
                        <a href="<?= BASE_PATH ?>/pages/sales/edit.php?order_id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-success btn-action">売上</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($orders)): ?>
                <tr><td colspan="8" class="text-center text-muted">データがありません</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if ($totalCount > 0): ?>
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="text-muted">
                全 <?= formatNumber($totalCount) ?> 件中 <?= formatNumber($offset + 1) ?>～<?= formatNumber(min($offset + $perPage, $totalCount)) ?> 件を表示
            </div>
            <?php if ($totalPages > 1): ?>
            <nav aria-label="ページネーション">
                <ul class="pagination mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= h(orderPageUrl($page - 1)) ?>">前へ</a>
                    </li>
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $start + 4);
                    $start = max(1, $end - 4);
                    ?>
                    <?php if ($start > 1): ?>
                    <li class="page-item"><a class="page-link" href="<?= h(orderPageUrl(1)) ?>">1</a></li>
                    <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                    <?php endif; ?>
                    <?php for ($i = $start; $i <= $end; $i++): ?>
                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                        <a class="page-link" href="<?= h(orderPageUrl($i)) ?>"><?= $i ?></a>
                    </li>
                    <?php endfor; ?>
                    <?php if ($end < $totalPages): ?>
                    <?php if ($end < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
                    <li class="page-item"><a class="page-link" href="<?= h(orderPageUrl($totalPages)) ?>"><?= $totalPages ?></a></li>
                    <?php endif; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="<?= h(orderPageUrl($page + 1)) ?>">次へ</a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
