<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/init_db.php';

$pdo = getDB();

$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-t');
$exported = $_GET['exported'] ?? '';
$download = isset($_GET['download']);

$stmt = $pdo->query("SELECT code FROM accounts WHERE name LIKE '%売掛金%' LIMIT 1");
$receivableCode = $stmt->fetchColumn() ?: '1310';

$stmt = $pdo->query("SELECT code, tax_class_code FROM accounts WHERE name LIKE '%売上%' LIMIT 1");
$salesAccount = $stmt->fetch();
$salesCode = $salesAccount['code'] ?? '4110';
$taxClassCode = $salesAccount['tax_class_code'] ?? '0060';

$columnDefs = [
    'sales_date' => ['label' => '売上日', 'value' => fn(array $s) => date('Y/m/d', strtotime($s['sales_date']))],
    'sales_no' => ['label' => '売上番号', 'value' => fn(array $s) => $s['sales_no']],
    'invoice_no' => ['label' => '請求書番号', 'value' => fn(array $s) => $s['invoice_no'] ?? ''],
    'order_no' => ['label' => '受注番号', 'value' => fn(array $s) => $s['order_no']],
    'customer_code' => ['label' => '顧客コード', 'value' => fn(array $s) => $s['customer_code']],
    'customer_name' => ['label' => '顧客名', 'value' => fn(array $s) => $s['customer_name']],
    'customer_accounting_code' => ['label' => '顧客勘定科目コード', 'value' => fn(array $s) => $s['customer_accounting_code'] ?? ''],
    'debit_account_code' => ['label' => '借方勘定科目コード', 'value' => fn(array $s) => $receivableCode],
    'credit_account_code' => ['label' => '貸方勘定科目コード', 'value' => fn(array $s) => $salesCode],
    'tax_class_code' => ['label' => '税区分コード', 'value' => fn(array $s) => $taxClassCode],
    'amount_excluding_tax' => ['label' => '税抜金額', 'value' => fn(array $s) => (int)$s['total_amount'] - (int)$s['tax_amount']],
    'tax_amount' => ['label' => '消費税額', 'value' => fn(array $s) => (int)$s['tax_amount']],
    'total_amount' => ['label' => '合計金額', 'value' => fn(array $s) => (int)$s['total_amount']],
    'note' => ['label' => '摘要', 'value' => fn(array $s) => "売上計上 {$s['sales_no']} {$s['customer_name']}"]
];

const COLUMNS_COOKIE = 'sales_export_columns';

$requestedColumns = array_values(array_intersect((array)($_GET['columns'] ?? []), array_keys($columnDefs)));
$savedColumns = array_values(array_intersect(explode(',', $_COOKIE[COLUMNS_COOKIE] ?? ''), array_keys($columnDefs)));

$noColumnSelected = isset($_GET['columns_submitted']) && empty($requestedColumns);

if ($requestedColumns) {
    $selectedColumns = $requestedColumns;
    setcookie(COLUMNS_COOKIE, implode(',', $selectedColumns), time() + 60 * 60 * 24 * 365, BASE_PATH . '/');
} elseif ($savedColumns) {
    $selectedColumns = $savedColumns;
} else {
    $selectedColumns = array_keys($columnDefs);
}

$sql = "
    SELECT s.*, c.code as customer_code, c.name as customer_name, c.accounting_code as customer_accounting_code, o.order_no
    FROM sales s
    JOIN customers c ON s.customer_id = c.id
    JOIN orders o ON s.order_id = o.id
    WHERE s.sales_date BETWEEN ? AND ?
";
$params = [$dateFrom, $dateTo];
if ($exported !== '') {
    $sql .= " AND s.exported = ?";
    $params[] = $exported;
}
$sql .= " ORDER BY s.sales_date ASC, s.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$salesList = $stmt->fetchAll();

$csvHeader = array_map(fn(string $key) => $columnDefs[$key]['label'], $selectedColumns);

function exportRow(array $sale, array $columnDefs, array $selectedColumns): array {
    return array_map(fn(string $key) => $columnDefs[$key]['value']($sale), $selectedColumns);
}

if ($download) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="sales_accounting_' . str_replace('-', '', $dateFrom) . '_' . str_replace('-', '', $dateTo) . '.csv"');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $csvHeader, ',', '"', '');
    foreach ($salesList as $sale) {
        fputcsv($out, exportRow($sale, $columnDefs, $selectedColumns), ',', '"', '');
    }
    fclose($out);
    exit;
}

$pageTitle = '売上CSV出力';
require_once __DIR__ . '/../../includes/header.php';

$totalAmount = array_sum(array_column($salesList, 'total_amount'));
$downloadQuery = http_build_query([
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'exported' => $exported,
    'columns' => $selectedColumns,
    'download' => 1
]);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-filetype-csv"></i> 売上CSV出力（会計システム連携）</h2>
    <a href="list.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> 売上一覧へ</a>
</div>

<?php if ($noColumnSelected): ?>
<div class="alert alert-warning">出力する列が選択されていないため、前回の列構成を表示しています。</div>
<?php endif; ?>

<div class="card mb-4">
    <div class="card-body">
        <form method="get">
            <input type="hidden" name="columns_submitted" value="1">
            <div class="row g-3 align-items-end mb-3">
                <div class="col-md-3">
                    <label class="form-label">売上日（自）</label>
                    <input type="date" name="date_from" class="form-control" value="<?= h($dateFrom) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">売上日（至）</label>
                    <input type="date" name="date_to" class="form-control" value="<?= h($dateTo) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">出力状態</label>
                    <select name="exported" class="form-select">
                        <option value="">すべて</option>
                        <option value="0" <?= $exported === '0' ? 'selected' : '' ?>>未出力</option>
                        <option value="1" <?= $exported === '1' ? 'selected' : '' ?>>出力済</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-outline-primary">絞り込み・列を反映</button>
                    <a href="?<?= h($downloadQuery) ?>" class="btn btn-primary <?= empty($salesList) ? 'disabled' : '' ?>"><i class="bi bi-download"></i> CSVダウンロード</a>
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center">
                <label class="form-label mb-0">出力する列</label>
                <div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="checkAllColumns">すべて選択</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="uncheckAllColumns">すべて解除</button>
                </div>
            </div>
            <div class="row row-cols-2 row-cols-md-4 g-2 mt-2">
                <?php foreach ($columnDefs as $key => $def): ?>
                <div class="col">
                    <div class="form-check">
                        <input class="form-check-input column-check" type="checkbox" name="columns[]" value="<?= h($key) ?>" id="col_<?= h($key) ?>" <?= in_array($key, $selectedColumns, true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="col_<?= h($key) ?>"><?= h($def['label']) ?></label>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>出力対象プレビュー（<?= count($salesList) ?>件）</span>
        <span>合計 &yen;<?= formatNumber($totalAmount) ?></span>
    </div>
    <div class="card-body table-responsive">
        <table class="table table-sm table-striped">
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
                    <?php foreach (exportRow($sale, $columnDefs, $selectedColumns) as $value): ?>
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

<script>
document.getElementById('checkAllColumns').addEventListener('click', function () {
    document.querySelectorAll('.column-check').forEach(cb => cb.checked = true);
});
document.getElementById('uncheckAllColumns').addEventListener('click', function () {
    document.querySelectorAll('.column-check').forEach(cb => cb.checked = false);
});
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
