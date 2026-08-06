<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/init_db.php';

initializeDatabase();

$pdo = getDB();

$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$download = isset($_GET['download']);

$sql = "SELECT s.*, o.order_no, c.code as customer_code, c.name as customer_name, c.accounting_code as customer_accounting_code
        FROM sales s
        JOIN orders o ON s.order_id = o.id
        JOIN customers c ON s.customer_id = c.id
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

$sql .= " ORDER BY s.sales_date, s.id";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$salesList = $stmt->fetchAll();

$stmt = $pdo->query("SELECT code, tax_class_code FROM accounts WHERE name LIKE '%売掛金%' LIMIT 1");
$receivable = $stmt->fetch() ?: ['code' => '1310', 'tax_class_code' => ''];

$stmt = $pdo->query("SELECT code, tax_class_code FROM accounts WHERE name LIKE '%売上%' LIMIT 1");
$salesAccount = $stmt->fetch() ?: ['code' => '4110', 'tax_class_code' => '0060'];

$csvHeader = [
    '売上日', '売上番号', '請求書番号', '受注番号', '顧客コード', '顧客名',
    '顧客勘定科目コード', '借方科目コード', '貸方科目コード', '税区分コード',
    '税抜金額', '消費税額', '税込金額', '摘要',
];

function buildExportRow(array $sale, array $receivable, array $salesAccount): array {
    return [
        date('Y/m/d', strtotime($sale['sales_date'])),
        $sale['sales_no'],
        $sale['invoice_no'] ?? '',
        $sale['order_no'],
        $sale['customer_code'],
        $sale['customer_name'],
        $sale['customer_accounting_code'] ?? '',
        $receivable['code'],
        $salesAccount['code'],
        $salesAccount['tax_class_code'] ?? '',
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
    // ExcelでUTF-8として認識させるためのBOM
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $csvHeader, ',', '"', '', "\r\n");
    foreach ($salesList as $sale) {
        fputcsv($out, buildExportRow($sale, $receivable, $salesAccount), ',', '"', '', "\r\n");
    }
    fclose($out);
    exit;
}

$pageTitle = '売上会計連携CSV出力';
require_once __DIR__ . '/../../includes/header.php';

$downloadQuery = http_build_query([
    'date_from' => $date_from,
    'date_to' => $date_to,
    'download' => 1,
]);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-file-earmark-spreadsheet"></i> 売上会計連携CSV出力</h2>
    <a href="list.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> 売上一覧へ戻る</a>
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
            <div class="col-12">
                <button type="submit" class="btn btn-outline-primary">絞り込み</button>
                <a href="export.php" class="btn btn-outline-secondary">クリア</a>
                <a href="export.php?<?= h($downloadQuery) ?>" class="btn btn-primary <?= empty($salesList) ? 'disabled' : '' ?>"><i class="bi bi-download"></i> CSVダウンロード</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">出力対象（<?= count($salesList) ?>件）</div>
    <div class="card-body table-responsive">
        <table class="table table-striped">
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
                    <?php foreach (buildExportRow($sale, $receivable, $salesAccount) as $value): ?>
                    <td><?= h((string)$value) ?></td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($salesList)): ?>
                <tr><td colspan="<?= count($csvHeader) ?>" class="text-center text-muted">データがありません</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
