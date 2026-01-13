<?php
$pageTitle = '売上一覧';
require_once __DIR__ . '/../../includes/header.php';

$pdo = getDB();

$search = $_GET['search'] ?? '';
$exported = $_GET['exported'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$sql = "SELECT s.*, c.name as customer_name, o.order_no FROM sales s JOIN customers c ON s.customer_id = c.id JOIN orders o ON s.order_id = o.id WHERE 1=1";
$params = [];

if ($search) {
    $sql .= " AND (s.sales_no LIKE ? OR s.invoice_no LIKE ? OR c.name LIKE ? OR o.order_no LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($exported !== '') {
    $sql .= " AND s.exported = ?";
    $params[] = $exported;
}
if ($date_from) {
    $sql .= " AND s.sales_date >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $sql .= " AND s.sales_date <= ?";
    $params[] = $date_to;
}

$sql .= " ORDER BY s.sales_date DESC, s.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$salesList = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'export_csv') {
        $ids = $_POST['ids'] ?? [];
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("SELECT s.*, c.name as customer_name, c.accounting_code as customer_accounting_code FROM sales s JOIN customers c ON s.customer_id = c.id WHERE s.id IN ($placeholders)");
            $stmt->execute($ids);
            $exportData = $stmt->fetchAll();
            
            $stmt = $pdo->query("SELECT code FROM accounts WHERE name LIKE '%売掛金%' LIMIT 1");
            $receivableCode = $stmt->fetchColumn() ?: '1310';
            
            $stmt = $pdo->query("SELECT code FROM accounts WHERE name LIKE '%売上%' LIMIT 1");
            $salesCode = $stmt->fetchColumn() ?: '4110';
            
            $csv = "";
            foreach ($exportData as $row) {
                $taxClassCode = '0060';
                $line = [
                    date('Y/m/d', strtotime($row['sales_date'])),
                    $receivableCode,
                    '',
                    '',
                    $row['customer_accounting_code'] ?? '',
                    '0000',
                    $row['total_amount'],
                    $salesCode,
                    '',
                    '',
                    '',
                    $taxClassCode,
                    $row['total_amount'],
                    "売上計上 {$row['sales_no']} {$row['customer_name']}"
                ];
                $csv .= implode(',', $line) . "\r\n";
            }
            
            $stmt = $pdo->prepare("UPDATE sales SET exported = 1, updated_at = CURRENT_TIMESTAMP WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            
            $csv = mb_convert_encoding($csv, 'SJIS', 'UTF-8');
            
            header('Content-Type: text/csv; charset=Shift_JIS');
            header('Content-Disposition: attachment; filename="sales_export_' . date('Ymd_His') . '.csv"');
            echo $csv;
            exit;
        }
    } elseif ($action === 'reset_export') {
        $ids = $_POST['ids'] ?? [];
        if (!empty($ids)) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmt = $pdo->prepare("UPDATE sales SET exported = 0, updated_at = CURRENT_TIMESTAMP WHERE id IN ($placeholders)");
            $stmt->execute($ids);
            header("Location: list.php");
            exit;
        }
    }
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-graph-up"></i> 売上一覧</h2>
    <a href="edit.php" class="btn btn-primary"><i class="bi bi-plus"></i> 新規売上</a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-3">
                <input type="text" name="search" class="form-control" placeholder="売上番号・請求書番号・顧客名で検索" value="<?= h($search) ?>">
            </div>
            <div class="col-md-2">
                <input type="date" name="date_from" class="form-control" placeholder="開始日" value="<?= h($date_from) ?>">
            </div>
            <div class="col-md-2">
                <input type="date" name="date_to" class="form-control" placeholder="終了日" value="<?= h($date_to) ?>">
            </div>
            <div class="col-md-2">
                <select name="exported" class="form-select">
                    <option value="">出力状態</option>
                    <option value="0" <?= $exported === '0' ? 'selected' : '' ?>>未出力</option>
                    <option value="1" <?= $exported === '1' ? 'selected' : '' ?>>出力済</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-outline-primary">検索</button>
                <a href="list.php" class="btn btn-outline-secondary">クリア</a>
            </div>
        </form>
    </div>
</div>

<form method="post" id="listForm">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>売上データ</span>
            <div>
                <button type="submit" name="action" value="export_csv" class="btn btn-sm btn-success" onclick="return confirm('選択した売上をCSV出力しますか？')"><i class="bi bi-download"></i> CSV出力</button>
                <button type="submit" name="action" value="reset_export" class="btn btn-sm btn-warning" onclick="return confirm('選択した売上の出力済フラグを解除しますか？')"><i class="bi bi-arrow-counterclockwise"></i> 出力済解除</button>
            </div>
        </div>
        <div class="card-body">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="checkAll"></th>
                        <th>売上番号</th>
                        <th>売上日</th>
                        <th>顧客名</th>
                        <th>受注番号</th>
                        <th>請求書番号</th>
                        <th class="text-end">金額</th>
                        <th>出力</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($salesList as $sale): ?>
                    <tr>
                        <td><input type="checkbox" name="ids[]" value="<?= $sale['id'] ?>" class="sale-check"></td>
                        <td><?= h($sale['sales_no']) ?></td>
                        <td><?= formatDate($sale['sales_date']) ?></td>
                        <td><?= h($sale['customer_name']) ?></td>
                        <td><?= h($sale['order_no']) ?></td>
                        <td><?= h($sale['invoice_no']) ?></td>
                        <td class="text-end">&yen;<?= formatNumber($sale['total_amount']) ?></td>
                        <td>
                            <?php if ($sale['exported']): ?>
                            <span class="badge bg-success">出力済</span>
                            <?php else: ?>
                            <span class="badge bg-secondary">未出力</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="edit.php?id=<?= $sale['id'] ?>" class="btn btn-sm btn-outline-primary btn-action">編集</a>
                            <a href="/reports/invoice.php?id=<?= $sale['id'] ?>" class="btn btn-sm btn-outline-secondary btn-action" target="_blank">請求書</a>
                            <a href="/reports/acceptance.php?id=<?= $sale['id'] ?>" class="btn btn-sm btn-outline-secondary btn-action" target="_blank">検収書</a>
                            <a href="/reports/sales_slip.php?id=<?= $sale['id'] ?>" class="btn btn-sm btn-outline-secondary btn-action" target="_blank">伝票</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($salesList)): ?>
                    <tr><td colspan="9" class="text-center text-muted">データがありません</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</form>

<script>
document.getElementById('checkAll').addEventListener('change', function() {
    document.querySelectorAll('.sale-check').forEach(cb => cb.checked = this.checked);
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
