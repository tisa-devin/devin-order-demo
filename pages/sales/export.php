<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/init_db.php';

$pdo = getDB();

$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$exportedFilter = $_GET['exported'] ?? '';
$download = ($_GET['download'] ?? '') === '1';

$sql = "SELECT s.*, c.code as customer_code, c.name as customer_name,
               c.accounting_code as customer_accounting_code, o.order_no
        FROM sales s
        JOIN customers c ON s.customer_id = c.id
        JOIN orders o ON s.order_id = o.id
        WHERE 1=1";
$params = [];

if ($date_from !== '') {
    $sql .= " AND s.sales_date >= ?";
    $params[] = $date_from;
}
if ($date_to !== '') {
    $sql .= " AND s.sales_date <= ?";
    $params[] = $date_to;
}
if ($exportedFilter !== '') {
    $sql .= " AND s.exported = ?";
    $params[] = $exportedFilter;
}

$sql .= " ORDER BY s.sales_date ASC, s.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$salesList = $stmt->fetchAll();

$receivableCode = $pdo->query("SELECT code FROM accounts WHERE name LIKE '%売掛金%' LIMIT 1")->fetchColumn() ?: '1310';
$salesAccount = $pdo->query("SELECT code, tax_class_code FROM accounts WHERE name LIKE '%売上%' LIMIT 1")->fetch();
$salesCode = $salesAccount['code'] ?? '4110';
$taxClassCode = $salesAccount['tax_class_code'] ?? '0060';

$csvHeader = [
    '売上日', '売上番号', '請求書番号', '受注番号', '顧客コード', '顧客名',
    '顧客勘定科目コード', '借方勘定科目コード', '貸方勘定科目コード', '税区分コード',
    '税抜金額', '消費税額', '合計金額', '摘要',
];

function buildExportRow(array $sale, string $receivableCode, string $salesCode, string $taxClassCode): array {
    return [
        date('Y/m/d', strtotime($sale['sales_date'])),
        $sale['sales_no'],
        $sale['invoice_no'] ?? '',
        $sale['order_no'] ?? '',
        $sale['customer_code'] ?? '',
        $sale['customer_name'] ?? '',
        $sale['customer_accounting_code'] ?? '',
        $receivableCode,
        $salesCode,
        $taxClassCode,
        (int)$sale['total_amount'] - (int)$sale['tax_amount'],
        (int)$sale['tax_amount'],
        (int)$sale['total_amount'],
        "売上計上 {$sale['sales_no']} {$sale['customer_name']}",
    ];
}

if ($download) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="sales_accounting_' . date('Ymd_His') . '.csv"');

    $out = fopen('php://output', 'w');
    // Excelでの文字化けを防ぐためUTF-8のBOMを付与する
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $csvHeader, ',', '"', '');
    foreach ($salesList as $sale) {
        fputcsv($out, buildExportRow($sale, $receivableCode, $salesCode, $taxClassCode), ',', '"', '');
    }
    fclose($out);
    exit;
}

$pageTitle = '会計連携CSV出力';
require_once __DIR__ . '/../../includes/header.php';

$downloadQuery = http_build_query([
    'date_from' => $date_from,
    'date_to' => $date_to,
    'exported' => $exportedFilter,
    'download' => '1',
]);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-filetype-csv"></i> 会計連携CSV出力</h2>
    <a href="list.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> 売上一覧へ</a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-3">
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
                    <option value="">すべて</option>
                    <option value="0" <?= $exportedFilter === '0' ? 'selected' : '' ?>>未出力</option>
                    <option value="1" <?= $exportedFilter === '1' ? 'selected' : '' ?>>出力済</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-outline-primary">絞り込み</button>
                <a href="export.php" class="btn btn-outline-secondary">条件クリア</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>対象データ <?= count($salesList) ?> 件（文字コード: UTF-8 BOM付き）</span>
        <a href="export.php?<?= h($downloadQuery) ?>" class="btn btn-sm btn-success <?= empty($salesList) ? 'disabled' : '' ?>">
            <i class="bi bi-download"></i> CSVダウンロード
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-sm">
                <thead>
                    <tr>
                        <?php foreach ($csvHeader as $column): ?>
                        <th><?= h($column) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($salesList as $sale): ?>
                    <tr>
                        <?php foreach (buildExportRow($sale, $receivableCode, $salesCode, $taxClassCode) as $value): ?>
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
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
