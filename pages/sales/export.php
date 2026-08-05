<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/init_db.php';

$pdo = getDB();

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-t');
$unexportedOnly = isset($_GET['unexported_only']);
$markExported = isset($_GET['mark_exported']);
$download = ($_GET['action'] ?? '') === 'download';

$sql = "
    SELECT s.*, c.code as customer_code, c.name as customer_name, c.accounting_code as customer_accounting_code, o.order_no
    FROM sales s
    JOIN customers c ON s.customer_id = c.id
    JOIN orders o ON s.order_id = o.id
    WHERE s.sales_date >= ? AND s.sales_date <= ?
";
$params = [$dateFrom, $dateTo];
if ($unexportedOnly) {
    $sql .= " AND s.exported = 0";
}
$sql .= " ORDER BY s.sales_date ASC, s.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$receivable = $pdo->query("SELECT code FROM accounts WHERE name LIKE '%売掛金%' LIMIT 1")->fetch();
$receivableCode = $receivable['code'] ?? '1310';

$salesAccount = $pdo->query("SELECT code, tax_class_code FROM accounts WHERE name = '売上高' LIMIT 1")->fetch();
$salesAccountCode = $salesAccount['code'] ?? '4110';
$taxClassCode = $salesAccount['tax_class_code'] ?? '0060';

$csvHeader = [
    '売上日', '売上番号', '受注番号', '請求書番号',
    '顧客コード', '顧客名', '顧客勘定科目コード',
    '借方科目コード', '貸方科目コード', '税区分コード',
    '税抜金額', '消費税額', '税込金額', '摘要',
];

function buildCsvRow(array $row, string $receivableCode, string $salesAccountCode, string $taxClassCode): array {
    $taxAmount = (int)$row['tax_amount'];
    $totalAmount = (int)$row['total_amount'];
    return [
        date('Y/m/d', strtotime($row['sales_date'])),
        $row['sales_no'],
        $row['order_no'],
        $row['invoice_no'] ?? '',
        $row['customer_code'] ?? '',
        $row['customer_name'],
        $row['customer_accounting_code'] ?? '',
        $receivableCode,
        $salesAccountCode,
        $taxClassCode,
        $totalAmount - $taxAmount,
        $taxAmount,
        $totalAmount,
        "売上計上 {$row['sales_no']} {$row['customer_name']}",
    ];
}

if ($download) {
    if ($markExported && !empty($rows)) {
        $ids = array_column($rows, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $update = $pdo->prepare("UPDATE sales SET exported = 1, updated_at = CURRENT_TIMESTAMP WHERE id IN ($placeholders)");
        $update->execute($ids);
    }

    $filename = 'sales_accounting_' . str_replace('-', '', $dateFrom) . '_' . str_replace('-', '', $dateTo) . '.csv';

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $csvHeader, ',', '"', '');
    foreach ($rows as $row) {
        fputcsv($out, buildCsvRow($row, $receivableCode, $salesAccountCode, $taxClassCode), ',', '"', '');
    }
    fclose($out);
    exit;
}

$pageTitle = '会計連携CSV出力';
require_once __DIR__ . '/../../includes/header.php';

$totalAmount = array_sum(array_column($rows, 'total_amount'));
$downloadQuery = http_build_query(array_filter([
    'action' => 'download',
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'unexported_only' => $unexportedOnly ? '1' : null,
    'mark_exported' => $markExported ? '1' : null,
]));
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-file-earmark-spreadsheet"></i> 会計連携CSV出力</h2>
    <a href="list.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> 売上一覧へ戻る</a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">売上日（自）</label>
                <input type="date" name="date_from" class="form-control" value="<?= h($dateFrom) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">売上日（至）</label>
                <input type="date" name="date_to" class="form-control" value="<?= h($dateTo) ?>" required>
            </div>
            <div class="col-md-3">
                <div class="form-check">
                    <input type="checkbox" name="unexported_only" value="1" class="form-check-input" id="unexportedOnly" <?= $unexportedOnly ? 'checked' : '' ?>>
                    <label class="form-check-label" for="unexportedOnly">未出力のみ</label>
                </div>
                <div class="form-check">
                    <input type="checkbox" name="mark_exported" value="1" class="form-check-input" id="markExported" <?= $markExported ? 'checked' : '' ?>>
                    <label class="form-check-label" for="markExported">出力後に「出力済」にする</label>
                </div>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-outline-primary">絞り込み</button>
                <a href="export.php?<?= h($downloadQuery) ?>" class="btn btn-primary <?= empty($rows) ? 'disabled' : '' ?>"><i class="bi bi-download"></i> CSVダウンロード</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>出力対象プレビュー（<?= count($rows) ?>件）</span>
        <span>合計 &yen;<?= formatNumber($totalAmount) ?></span>
    </div>
    <div class="card-body">
        <p class="text-muted small">文字コードはUTF-8（BOM付き）で出力するため、Excelでそのまま開けます。</p>
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
                    <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach (buildCsvRow($row, $receivableCode, $salesAccountCode, $taxClassCode) as $i => $value): ?>
                        <td class="<?= in_array($i, [10, 11, 12], true) ? 'text-end' : '' ?>"><?= h($value) ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($rows)): ?>
                    <tr><td colspan="<?= count($csvHeader) ?>" class="text-center text-muted">対象データがありません</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
