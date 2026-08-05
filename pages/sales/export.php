<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/init_db.php';

$pdo = getDB();

$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-t');
$include_exported = isset($_GET['include_exported']);
$mark_exported = isset($_GET['mark_exported']);

$fromSql = " FROM sales s
             JOIN customers c ON s.customer_id = c.id
             LEFT JOIN orders o ON s.order_id = o.id
             WHERE s.sales_date BETWEEN ? AND ?";
$params = [$date_from, $date_to];
if (!$include_exported) {
    $fromSql .= " AND s.exported = 0";
}

$selectSql = "SELECT s.*, c.code as customer_code, c.name as customer_name, c.accounting_code as customer_accounting_code, o.order_no";
$orderSql = " ORDER BY s.sales_date ASC, s.id ASC";

// 会計システム側の相手科目（売掛金・売上高）はマスタから取得する
$receivable = $pdo->query("SELECT code, name, tax_class_code FROM accounts WHERE name LIKE '%売掛金%' LIMIT 1")->fetch()
    ?: ['code' => '1310', 'name' => '売掛金', 'tax_class_code' => '0000'];
$salesAccount = $pdo->query("SELECT code, name, tax_class_code FROM accounts WHERE name LIKE '%売上高%' LIMIT 1")->fetch()
    ?: ['code' => '4110', 'name' => '売上高', 'tax_class_code' => '0060'];

// 出力可能な列定義（キー => 見出しと値の組み立て方）
$columnDefs = [
    'sales_date' => ['label' => '売上日', 'value' => fn($s) => date('Y/m/d', strtotime($s['sales_date']))],
    'sales_no' => ['label' => '売上番号', 'value' => fn($s) => $s['sales_no']],
    'invoice_no' => ['label' => '請求書番号', 'value' => fn($s) => $s['invoice_no'] ?? ''],
    'order_no' => ['label' => '受注番号', 'value' => fn($s) => $s['order_no'] ?? ''],
    'customer_code' => ['label' => '顧客コード', 'value' => fn($s) => $s['customer_code']],
    'customer_name' => ['label' => '顧客名', 'value' => fn($s) => $s['customer_name']],
    'customer_accounting_code' => ['label' => '顧客勘定科目コード', 'value' => fn($s) => $s['customer_accounting_code'] ?? ''],
    'debit_code' => ['label' => '借方科目コード', 'value' => fn($s) => $receivable['code']],
    'debit_name' => ['label' => '借方科目名', 'value' => fn($s) => $receivable['name']],
    'credit_code' => ['label' => '貸方科目コード', 'value' => fn($s) => $salesAccount['code']],
    'credit_name' => ['label' => '貸方科目名', 'value' => fn($s) => $salesAccount['name']],
    'tax_class_code' => ['label' => '税区分コード', 'value' => fn($s) => $salesAccount['tax_class_code'] ?? ''],
    'amount_excluding_tax' => ['label' => '税抜金額', 'value' => fn($s) => $s['total_amount'] - $s['tax_amount']],
    'tax_amount' => ['label' => '消費税額', 'value' => fn($s) => $s['tax_amount']],
    'total_amount' => ['label' => '合計金額', 'value' => fn($s) => $s['total_amount']],
    'note' => ['label' => '摘要', 'value' => fn($s) => "売上計上 {$s['sales_no']} {$s['customer_name']}"],
];

// columns_set がない初回アクセスは全列を対象とする
$selectedColumns = isset($_GET['columns_set'])
    ? array_values(array_intersect(array_keys($columnDefs), (array)($_GET['columns'] ?? [])))
    : array_keys($columnDefs);
if (!$selectedColumns) {
    $selectedColumns = array_keys($columnDefs);
}

$csvHeader = array_map(fn($key) => $columnDefs[$key]['label'], $selectedColumns);

/**
 * 売上1件を選択された列のみのCSV1行に変換する
 */
function buildExportRow(array $sale, array $columnDefs, array $selectedColumns): array {
    return array_map(fn($key) => $columnDefs[$key]['value']($sale), $selectedColumns);
}

if (isset($_GET['download'])) {
    $stmt = $pdo->prepare($selectSql . $fromSql . $orderSql);
    $stmt->execute($params);
    $sales = $stmt->fetchAll();

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="sales_accounting_' . date('Ymd_His') . '.csv"');

    $out = fopen('php://output', 'w');
    // Excelで開いたときに文字化けしないようUTF-8のBOMを先頭に付与する
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $csvHeader, ',', '"', '');
    foreach ($sales as $sale) {
        fputcsv($out, buildExportRow($sale, $columnDefs, $selectedColumns), ',', '"', '');
    }
    fclose($out);

    if ($mark_exported && $sales) {
        $ids = array_column($sales, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("UPDATE sales SET exported = 1, updated_at = CURRENT_TIMESTAMP WHERE id IN ($placeholders)")->execute($ids);
    }
    exit;
}

$stmt = $pdo->prepare("SELECT COUNT(*), COALESCE(SUM(s.total_amount), 0)" . $fromSql);
$stmt->execute($params);
[$targetCount, $targetAmount] = $stmt->fetch(PDO::FETCH_NUM);

$stmt = $pdo->prepare($selectSql . $fromSql . $orderSql . " LIMIT 10");
$stmt->execute($params);
$previewSales = $stmt->fetchAll();

$pageTitle = '会計連携CSV出力';
require_once __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="bi bi-file-earmark-spreadsheet"></i> 会計連携CSV出力</h2>
    <a href="list.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> 売上一覧へ戻る</a>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" id="exportForm" class="row g-3 align-items-end">
            <div class="col-md-3">
                <label class="form-label">売上日（自）</label>
                <input type="date" name="date_from" class="form-control" value="<?= h($date_from) ?>" required>
            </div>
            <div class="col-md-3">
                <label class="form-label">売上日（至）</label>
                <input type="date" name="date_to" class="form-control" value="<?= h($date_to) ?>" required>
            </div>
            <div class="col-md-3">
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="include_exported" name="include_exported" value="1" <?= $include_exported ? 'checked' : '' ?>>
                    <label class="form-check-label" for="include_exported">出力済も含める</label>
                </div>
                <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="mark_exported" name="mark_exported" value="1" <?= $mark_exported ? 'checked' : '' ?>>
                    <label class="form-check-label" for="mark_exported">出力後に出力済にする</label>
                </div>
            </div>
            <div class="col-12">
                <input type="hidden" name="columns_set" value="1">
                <label class="form-label">出力する列</label>
                <div class="d-flex flex-wrap gap-3">
                    <?php foreach ($columnDefs as $key => $column): ?>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input column-check" id="column_<?= h($key) ?>" name="columns[]" value="<?= h($key) ?>" <?= in_array($key, $selectedColumns, true) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="column_<?= h($key) ?>"><?= h($column['label']) ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-outline-primary">絞り込み</button>
                <button type="submit" name="download" value="1" class="btn btn-success" <?= $targetCount === 0 ? 'disabled' : '' ?>><i class="bi bi-download"></i> CSV出力</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>出力対象</span>
        <span class="text-muted small">対象<?= formatNumber($targetCount) ?>件 / 合計&yen;<?= formatNumber($targetAmount) ?>（先頭10件をプレビュー表示）</span>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-striped">
                <thead>
                    <tr>
                        <?php foreach ($csvHeader as $column): ?>
                        <th><?= h($column) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($previewSales as $sale): ?>
                    <tr>
                        <?php foreach (buildExportRow($sale, $columnDefs, $selectedColumns) as $value): ?>
                        <td><?= h($value) ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($previewSales)): ?>
                    <tr><td colspan="<?= count($csvHeader) ?>" class="text-center text-muted">対象の売上がありません</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <p class="text-muted small mb-0">文字コードはUTF-8（BOM付き）です。Excelでそのまま開けます。</p>
    </div>
</div>

<script>
    // 前回選んだ列構成を復元し、変更の都度保存する
    const COLUMNS_KEY = 'salesExportColumns';
    const columnChecks = document.querySelectorAll('.column-check');
    const saved = localStorage.getItem(COLUMNS_KEY);

    <?php if (!isset($_GET['columns_set'])): ?>
    // 初回表示時は保存された列構成を適用し、プレビューを揃えるために再送信する
    if (saved) {
        const selected = JSON.parse(saved);
        columnChecks.forEach(check => { check.checked = selected.includes(check.value); });
        document.getElementById('exportForm').submit();
    }
    <?php endif; ?>

    columnChecks.forEach(check => check.addEventListener('change', () => {
        const selected = Array.from(columnChecks).filter(c => c.checked).map(c => c.value);
        localStorage.setItem(COLUMNS_KEY, JSON.stringify(selected));
    }));
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
