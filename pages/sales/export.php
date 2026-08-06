<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/init_db.php';

const COLUMNS_COOKIE = 'sales_export_columns';

$pdo = getDB();

$date_from = $_GET['date_from'] ?? date('Y-m-01');
$date_to = $_GET['date_to'] ?? date('Y-m-d');
$exportedFilter = $_GET['exported'] ?? '';
$download = ($_GET['download'] ?? '') === '1';

$receivableCode = $pdo->query("SELECT code FROM accounts WHERE name LIKE '%売掛金%' LIMIT 1")->fetchColumn() ?: '1310';
$salesAccount = $pdo->query("SELECT code, tax_class_code FROM accounts WHERE name LIKE '%売上%' LIMIT 1")->fetch();
$salesCode = $salesAccount['code'] ?? '4110';
$taxClassCode = $salesAccount['tax_class_code'] ?? '0060';

// 出力可能な列の定義。キーが columns[] パラメータの値になる。
$columnDefs = [
    'sales_date' => ['label' => '売上日', 'value' => fn(array $s) => date('Y/m/d', strtotime($s['sales_date']))],
    'sales_no' => ['label' => '売上番号', 'value' => fn(array $s) => $s['sales_no']],
    'invoice_no' => ['label' => '請求書番号', 'value' => fn(array $s) => $s['invoice_no'] ?? ''],
    'order_no' => ['label' => '受注番号', 'value' => fn(array $s) => $s['order_no'] ?? ''],
    'customer_code' => ['label' => '顧客コード', 'value' => fn(array $s) => $s['customer_code'] ?? ''],
    'customer_name' => ['label' => '顧客名', 'value' => fn(array $s) => $s['customer_name'] ?? ''],
    'customer_accounting_code' => ['label' => '顧客勘定科目コード', 'value' => fn(array $s) => $s['customer_accounting_code'] ?? ''],
    'debit_account_code' => ['label' => '借方勘定科目コード', 'value' => fn(array $s) => $receivableCode],
    'credit_account_code' => ['label' => '貸方勘定科目コード', 'value' => fn(array $s) => $salesCode],
    'tax_class_code' => ['label' => '税区分コード', 'value' => fn(array $s) => $taxClassCode],
    'amount_ex_tax' => ['label' => '税抜金額', 'value' => fn(array $s) => (int)$s['total_amount'] - (int)$s['tax_amount']],
    'tax_amount' => ['label' => '消費税額', 'value' => fn(array $s) => (int)$s['tax_amount']],
    'total_amount' => ['label' => '合計金額', 'value' => fn(array $s) => (int)$s['total_amount']],
    'note' => ['label' => '摘要', 'value' => fn(array $s) => "売上計上 {$s['sales_no']} {$s['customer_name']}"],
];
$allColumnKeys = array_keys($columnDefs);

/**
 * 選択された出力列を決定する。
 * 優先順は「今回のリクエスト > 前回選択（クッキー）> 全列」。
 */
function resolveSelectedColumns(array $allColumnKeys): array {
    if (isset($_GET['columns_set'])) {
        $selected = array_values(array_intersect($allColumnKeys, (array)($_GET['columns'] ?? [])));
        if (!empty($selected)) {
            setcookie(COLUMNS_COOKIE, implode(',', $selected), [
                'expires' => time() + 60 * 60 * 24 * 365,
                'path' => BASE_PATH . '/',
                'samesite' => 'Lax',
            ]);
            return $selected;
        }
    }
    if (isset($_COOKIE[COLUMNS_COOKIE])) {
        $saved = array_values(array_intersect($allColumnKeys, explode(',', $_COOKIE[COLUMNS_COOKIE])));
        if (!empty($saved)) {
            return $saved;
        }
    }
    return $allColumnKeys;
}

$selectedColumns = resolveSelectedColumns($allColumnKeys);

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

$csvHeader = array_map(fn(string $key) => $columnDefs[$key]['label'], $selectedColumns);

$buildExportRow = function (array $sale) use ($columnDefs, $selectedColumns): array {
    $row = [];
    foreach ($selectedColumns as $key) {
        $row[] = $columnDefs[$key]['value']($sale);
    }
    return $row;
};

if ($download) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="sales_accounting_' . date('Ymd_His') . '.csv"');

    $out = fopen('php://output', 'w');
    // Excelでの文字化けを防ぐためUTF-8のBOMを付与する
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $csvHeader, ',', '"', '');
    foreach ($salesList as $sale) {
        fputcsv($out, $buildExportRow($sale), ',', '"', '');
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
    'columns_set' => '1',
    'columns' => $selectedColumns,
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
            <input type="hidden" name="columns_set" value="1">
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
            <div class="col-12">
                <label class="form-label mb-1">出力する列（前回の選択を記憶します）</label>
                <div class="row row-cols-2 row-cols-md-4 g-1">
                    <?php foreach ($columnDefs as $key => $def): ?>
                    <div class="col">
                        <div class="form-check">
                            <input class="form-check-input column-check" type="checkbox" name="columns[]" value="<?= h($key) ?>" id="col_<?= h($key) ?>" <?= in_array($key, $selectedColumns, true) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="col_<?= h($key) ?>"><?= h($def['label']) ?></label>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="mt-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="selectAllColumns">全選択</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="clearAllColumns">全解除</button>
                </div>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-outline-primary">条件・列を反映</button>
                <a href="export.php" class="btn btn-outline-secondary">条件クリア</a>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span>対象データ <?= count($salesList) ?> 件 / 出力列 <?= count($selectedColumns) ?> 列（文字コード: UTF-8 BOM付き）</span>
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
                        <?php foreach ($buildExportRow($sale) as $value): ?>
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

<script>
    (function () {
        function setAll(checked) {
            document.querySelectorAll('.column-check').forEach(function (el) { el.checked = checked; });
        }
        document.getElementById('selectAllColumns').addEventListener('click', function () { setAll(true); });
        document.getElementById('clearAllColumns').addEventListener('click', function () { setAll(false); });
    })();
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>
