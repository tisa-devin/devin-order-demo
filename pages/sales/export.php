<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/init_db.php';

$pdo = getDB();

$dateFrom = trim((string)($_GET['date_from'] ?? ''));
$dateTo = trim((string)($_GET['date_to'] ?? ''));
$exported = trim((string)($_GET['exported'] ?? ''));
$download = ($_GET['download'] ?? '') === '1';

$sql = "SELECT s.*, c.name AS customer_name, c.accounting_code AS customer_accounting_code, o.order_no
        FROM sales s
        JOIN customers c ON s.customer_id = c.id
        JOIN orders o ON s.order_id = o.id
        WHERE 1=1";
$params = [];

if ($dateFrom !== '') {
    $sql .= " AND s.sales_date >= ?";
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $sql .= " AND s.sales_date <= ?";
    $params[] = $dateTo;
}
if ($exported !== '') {
    $sql .= " AND s.exported = ?";
    $params[] = $exported;
}

$sql .= " ORDER BY s.sales_date ASC, s.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$salesList = $stmt->fetchAll();

$csvHeader = [
    '売上番号', '売上日', '顧客コード', '顧客名', '勘定科目コード',
    '受注番号', '請求書番号', '税抜金額', '消費税額', '合計金額', '摘要',
];

$accountingCode = $pdo->query("SELECT code FROM accounts WHERE name LIKE '%売上%' LIMIT 1")->fetchColumn() ?: '4110';

function buildExportRow(array $sale, string $accountingCode): array
{
    $total = (int)$sale['total_amount'];
    $tax = (int)$sale['tax_amount'];
    return [
        $sale['sales_no'],
        date('Y/m/d', strtotime($sale['sales_date'])),
        $sale['customer_accounting_code'] ?? '',
        $sale['customer_name'],
        $accountingCode,
        $sale['order_no'],
        $sale['invoice_no'] ?? '',
        $total - $tax,
        $tax,
        $total,
        "売上計上 {$sale['sales_no']}",
    ];
}

if ($download) {
    $filename = 'sales_accounting_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    // Excelで文字化けしないようUTF-8 BOMを付与する
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $csvHeader, ',', '"', '', "\r\n");
    foreach ($salesList as $sale) {
        fputcsv($out, buildExportRow($sale, $accountingCode), ',', '"', '', "\r\n");
    }
    fclose($out);
    exit;
}

$pageTitle = '会計連携CSV出力';
require_once __DIR__ . '/../../includes/header.php';

$downloadQuery = http_build_query([
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'exported' => $exported,
    'download' => '1',
]);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-file-earmark-spreadsheet"></i> 会計連携CSV出力</h2>
    <a href="list.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> 売上一覧へ</a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">売上日（自）</label>
                <input type="date" name="date_from" class="form-control" value="<?= h($dateFrom) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">売上日（至）</label>
                <input type="date" name="date_to" class="form-control" value="<?= h($dateTo) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label">出力状態</label>
                <select name="exported" class="form-select">
                    <option value="">すべて</option>
                    <option value="0" <?= $exported === '0' ? 'selected' : '' ?>>未出力</option>
                    <option value="1" <?= $exported === '1' ? 'selected' : '' ?>>出力済</option>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn btn-outline-primary me-2">絞り込み</button>
                <a href="export.php" class="btn btn-outline-secondary">条件クリア</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>出力対象 <?= count($salesList) ?> 件（UTF-8 BOM付きCSV）</span>
        <a href="export.php?<?= h($downloadQuery) ?>" class="btn btn-sm btn-success <?= empty($salesList) ? 'disabled' : '' ?>">
            <i class="bi bi-download"></i> CSVダウンロード
        </a>
    </div>
    <div class="card-body">
        <table class="table table-striped">
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
                    <?php foreach (buildExportRow($sale, $accountingCode) as $value): ?>
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

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
