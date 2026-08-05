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

$columnDefs = [
    'sales_date' => ['label' => '売上日', 'numeric' => false, 'value' => fn($r) => date('Y/m/d', strtotime($r['sales_date']))],
    'sales_no' => ['label' => '売上番号', 'numeric' => false, 'value' => fn($r) => $r['sales_no']],
    'order_no' => ['label' => '受注番号', 'numeric' => false, 'value' => fn($r) => $r['order_no']],
    'invoice_no' => ['label' => '請求書番号', 'numeric' => false, 'value' => fn($r) => $r['invoice_no'] ?? ''],
    'customer_code' => ['label' => '顧客コード', 'numeric' => false, 'value' => fn($r) => $r['customer_code'] ?? ''],
    'customer_name' => ['label' => '顧客名', 'numeric' => false, 'value' => fn($r) => $r['customer_name']],
    'customer_accounting_code' => ['label' => '顧客勘定科目コード', 'numeric' => false, 'value' => fn($r) => $r['customer_accounting_code'] ?? ''],
    'debit_code' => ['label' => '借方科目コード', 'numeric' => false, 'value' => fn($r) => $receivableCode],
    'credit_code' => ['label' => '貸方科目コード', 'numeric' => false, 'value' => fn($r) => $salesAccountCode],
    'tax_class_code' => ['label' => '税区分コード', 'numeric' => false, 'value' => fn($r) => $taxClassCode],
    'net_amount' => ['label' => '税抜金額', 'numeric' => true, 'value' => fn($r) => (int)$r['total_amount'] - (int)$r['tax_amount']],
    'tax_amount' => ['label' => '消費税額', 'numeric' => true, 'value' => fn($r) => (int)$r['tax_amount']],
    'total_amount' => ['label' => '税込金額', 'numeric' => true, 'value' => fn($r) => (int)$r['total_amount']],
    'note' => ['label' => '摘要', 'numeric' => false, 'value' => fn($r) => "売上計上 {$r['sales_no']} {$r['customer_name']}"],
];

const COLUMN_COOKIE = 'sales_export_columns';

if (isset($_GET['columns_submitted'])) {
    $selectedColumns = array_values(array_intersect(array_keys($columnDefs), (array)($_GET['columns'] ?? [])));
    setcookie(COLUMN_COOKIE, implode(',', $selectedColumns), time() + 60 * 60 * 24 * 365, '/');
} elseif (isset($_COOKIE[COLUMN_COOKIE])) {
    $selectedColumns = array_values(array_intersect(array_keys($columnDefs), explode(',', $_COOKIE[COLUMN_COOKIE])));
} else {
    $selectedColumns = array_keys($columnDefs);
}

if (empty($selectedColumns)) {
    $selectedColumns = array_keys($columnDefs);
}

$csvHeader = array_map(fn($key) => $columnDefs[$key]['label'], $selectedColumns);

$buildCsvRow = function (array $row) use ($columnDefs, $selectedColumns): array {
    return array_map(fn($key) => $columnDefs[$key]['value']($row), $selectedColumns);
};

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
        fputcsv($out, $buildCsvRow($row), ',', '"', '');
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
    'columns_submitted' => '1',
    'columns' => $selectedColumns,
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
            <div class="col-12">
                <label class="form-label">出力列</label>
                <div class="d-flex flex-wrap gap-3">
                    <?php foreach ($columnDefs as $key => $def): ?>
                    <div class="form-check">
                        <input type="checkbox" name="columns[]" value="<?= h($key) ?>" class="form-check-input column-check" id="col_<?= h($key) ?>" <?= in_array($key, $selectedColumns, true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="col_<?= h($key) ?>"><?= h($def['label']) ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAllColumns">全選択</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="clearAllColumns">全解除</button>
                    <span class="text-muted small ms-2">選択した列構成は「絞り込み」時に保存され、次回以降の初期値になります。</span>
                </div>
            </div>
            <input type="hidden" name="columns_submitted" value="1">
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
                        <?php foreach ($buildCsvRow($row) as $i => $value): ?>
                        <td class="<?= $columnDefs[$selectedColumns[$i]]['numeric'] ? 'text-end' : '' ?>"><?= h($value) ?></td>
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

<script>
document.getElementById('selectAllColumns').addEventListener('click', function () {
    document.querySelectorAll('.column-check').forEach(cb => cb.checked = true);
});
document.getElementById('clearAllColumns').addEventListener('click', function () {
    document.querySelectorAll('.column-check').forEach(cb => cb.checked = false);
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
