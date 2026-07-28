<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/init_db.php';

$pdo = getDB();

$date_from = $_GET['date_from'] ?? $_POST['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? $_POST['date_to'] ?? '';
$exported = $_GET['exported'] ?? $_POST['exported'] ?? '';

$where = "FROM sales s JOIN customers c ON s.customer_id = c.id JOIN orders o ON s.order_id = o.id WHERE 1=1";
$params = [];
if ($date_from) {
    $where .= " AND s.sales_date >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $where .= " AND s.sales_date <= ?";
    $params[] = $date_to;
}
if ($exported !== '') {
    $where .= " AND s.exported = ?";
    $params[] = $exported;
}

$selectColumns = "s.*, c.code as customer_code, c.name as customer_name, c.accounting_code as customer_accounting_code, o.order_no";
$orderBy = "s.sales_date ASC, s.id ASC";

$receivableCode = $pdo->query("SELECT code FROM accounts WHERE name LIKE '%売掛金%' LIMIT 1")->fetchColumn() ?: '1310';
$salesAccount = $pdo->query("SELECT code, tax_class_code FROM accounts WHERE name LIKE '%売上%' LIMIT 1")->fetch();
$salesCode = $salesAccount['code'] ?? '4110';
$taxClassCode = $salesAccount['tax_class_code'] ?? '0060';

$csvHeader = ['売上日', '売上番号', '請求書番号', '受注番号', '顧客コード', '顧客名', '顧客勘定科目コード', '借方科目コード', '貸方科目コード', '税区分コード', '税抜金額', '消費税額', '合計金額', '摘要'];

function csvRow(array $sale, string $receivableCode, string $salesCode, string $taxClassCode): array {
    $total = (int)$sale['total_amount'];
    $tax = (int)$sale['tax_amount'];
    return [
        date('Y/m/d', strtotime($sale['sales_date'])),
        $sale['sales_no'],
        $sale['invoice_no'] ?? '',
        $sale['order_no'],
        $sale['customer_code'] ?? '',
        $sale['customer_name'],
        $sale['customer_accounting_code'] ?? '',
        $receivableCode,
        $salesCode,
        $taxClassCode,
        $total - $tax,
        $tax,
        $total,
        "売上計上 {$sale['sales_no']} {$sale['customer_name']}",
    ];
}

if (($_POST['action'] ?? '') === 'download') {
    $stmt = $pdo->prepare("SELECT " . $selectColumns . " " . $where . " ORDER BY " . $orderBy);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    if (!empty($_POST['mark_exported'])) {
        $ids = array_column($rows, 'id');
        if ($ids) {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $pdo->prepare("UPDATE sales SET exported = 1, updated_at = CURRENT_TIMESTAMP WHERE id IN ($placeholders)")->execute($ids);
        }
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="sales_export_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    // Excel で UTF-8 と認識させるための BOM
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $csvHeader, ',', '"', '');
    foreach ($rows as $row) {
        fputcsv($out, csvRow($row, $receivableCode, $salesCode, $taxClassCode), ',', '"', '');
    }
    fclose($out);
    exit;
}

$pageTitle = '売上CSV出力';
require_once __DIR__ . '/../../includes/header.php';

$pagination = fetchPaginated($pdo, $selectColumns, $where, $orderBy, $params);
$salesList = $pagination['rows'];

$totalStmt = $pdo->prepare("SELECT COALESCE(SUM(s.total_amount), 0) " . $where);
$totalStmt->execute($params);
$totalAmount = (int)$totalStmt->fetchColumn();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-file-earmark-spreadsheet"></i> 売上CSV出力（会計システム連携）</h2>
    <a href="list.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> 売上一覧へ</a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">売上日（自）</label>
                <input type="date" name="date_from" class="form-control" value="<?= h($date_from) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">売上日（至）</label>
                <input type="date" name="date_to" class="form-control" value="<?= h($date_to) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">出力状態</label>
                <select name="exported" class="form-select">
                    <option value="">全て</option>
                    <option value="0" <?= $exported === '0' ? 'selected' : '' ?>>未出力</option>
                    <option value="1" <?= $exported === '1' ? 'selected' : '' ?>>出力済</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-outline-primary">絞り込み</button>
                <a href="export.php" class="btn btn-outline-secondary">クリア</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>対象 <?= formatNumber($pagination['totalCount']) ?> 件 / 合計 &yen;<?= formatNumber($totalAmount) ?></span>
        <form method="post" class="d-flex align-items-center gap-3">
            <input type="hidden" name="date_from" value="<?= h($date_from) ?>">
            <input type="hidden" name="date_to" value="<?= h($date_to) ?>">
            <input type="hidden" name="exported" value="<?= h($exported) ?>">
            <div class="form-check mb-0">
                <input type="checkbox" class="form-check-input" id="markExported" name="mark_exported" value="1" checked>
                <label class="form-check-label" for="markExported">出力済にする</label>
            </div>
            <button type="submit" name="action" value="download" class="btn btn-primary btn-sm" <?= $pagination['totalCount'] === 0 ? 'disabled' : '' ?>>
                <i class="bi bi-download"></i> CSVダウンロード（UTF-8 BOM付き）
            </button>
        </form>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-sm">
                <thead>
                    <tr>
                        <?php foreach ($csvHeader as $col): ?>
                        <th><?= h($col) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($salesList as $sale): ?>
                    <tr>
                        <?php foreach (csvRow($sale, $receivableCode, $salesCode, $taxClassCode) as $value): ?>
                        <td><?= h((string)$value) ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($salesList)): ?>
                    <tr><td colspan="<?= count($csvHeader) ?>" class="text-center text-muted">対象データがありません</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php renderPagination($pagination); ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
