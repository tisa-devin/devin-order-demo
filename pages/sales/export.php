<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/init_db.php';

$pdo = getDB();

$deliveryFrom = trim($_REQUEST['delivery_from'] ?? '');
$deliveryTo = trim($_REQUEST['delivery_to'] ?? '');

$sql = "SELECT s.*, o.order_no, o.delivery_date, c.name as customer_name, c.accounting_code as customer_accounting_code
        FROM sales s
        JOIN orders o ON s.order_id = o.id
        JOIN customers c ON s.customer_id = c.id
        WHERE 1=1";
$params = [];

if ($deliveryFrom !== '') {
    $sql .= " AND o.delivery_date >= ?";
    $params[] = $deliveryFrom;
}
if ($deliveryTo !== '') {
    $sql .= " AND o.delivery_date <= ?";
    $params[] = $deliveryTo;
}

$sql .= " ORDER BY o.delivery_date, s.sales_date, s.id";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$salesList = $stmt->fetchAll();

if (($_POST['action'] ?? '') === 'download') {
    $receivableCode = $pdo->query("SELECT code FROM accounts WHERE name LIKE '%売掛金%' LIMIT 1")->fetchColumn() ?: '1310';
    $salesCode = $pdo->query("SELECT code FROM accounts WHERE name LIKE '%売上%' LIMIT 1")->fetchColumn() ?: '4110';
    $taxClassCode = '0060';

    $rows = [[
        '売上番号', '売上日', '納期', '受注番号', '顧客コード', '顧客名',
        '借方科目コード', '貸方科目コード', '税区分コード', '税抜金額', '消費税額', '合計金額', '摘要'
    ]];
    foreach ($salesList as $row) {
        $total = (int)$row['total_amount'];
        $tax = (int)$row['tax_amount'];
        $rows[] = [
            $row['sales_no'],
            $row['sales_date'] ? date('Y/m/d', strtotime($row['sales_date'])) : '',
            $row['delivery_date'] ? date('Y/m/d', strtotime($row['delivery_date'])) : '',
            $row['order_no'],
            $row['customer_accounting_code'] ?? '',
            $row['customer_name'],
            $receivableCode,
            $salesCode,
            $taxClassCode,
            $total - $tax,
            $tax,
            $total,
            "売上計上 {$row['sales_no']} {$row['customer_name']}"
        ];
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="sales_accounting_' . date('Ymd_His') . '.csv"');
    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'w');
    foreach ($rows as $row) {
        fputcsv($out, $row, ',', '"', '', "\r\n");
    }
    fclose($out);
    exit;
}

$pageTitle = '売上CSV出力';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-file-earmark-spreadsheet"></i> 売上CSV出力（会計システム連携）</h2>
    <a href="list.php" class="btn btn-outline-secondary">売上一覧へ戻る</a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">納期（自）</label>
                <input type="date" name="delivery_from" class="form-control" value="<?= h($deliveryFrom) ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label">納期（至）</label>
                <input type="date" name="delivery_to" class="form-control" value="<?= h($deliveryTo) ?>">
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button type="submit" class="btn btn-outline-primary me-2">絞り込み</button>
                <a href="export.php" class="btn btn-outline-secondary">条件クリア</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>出力対象：<?= count($salesList) ?> 件</span>
        <form method="post">
            <input type="hidden" name="action" value="download">
            <input type="hidden" name="delivery_from" value="<?= h($deliveryFrom) ?>">
            <input type="hidden" name="delivery_to" value="<?= h($deliveryTo) ?>">
            <button type="submit" class="btn btn-sm btn-success" <?= empty($salesList) ? 'disabled' : '' ?>><i class="bi bi-download"></i> CSV出力（UTF-8 BOM付き）</button>
        </form>
    </div>
    <div class="card-body">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>売上番号</th>
                    <th>売上日</th>
                    <th>納期</th>
                    <th>受注番号</th>
                    <th>顧客名</th>
                    <th class="text-end">合計金額</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($salesList as $sale): ?>
                <tr>
                    <td><?= h($sale['sales_no']) ?></td>
                    <td><?= formatDate($sale['sales_date']) ?></td>
                    <td><?= formatDate($sale['delivery_date']) ?></td>
                    <td><?= h($sale['order_no']) ?></td>
                    <td><?= h($sale['customer_name']) ?></td>
                    <td class="text-end">&yen;<?= formatNumber($sale['total_amount']) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($salesList)): ?>
                <tr><td colspan="6" class="text-center text-muted">データがありません</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
